package com.example.mulitple;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.Service;
import android.content.Intent;
import android.hardware.camera2.CameraAccessException;
import android.hardware.camera2.CameraCharacteristics;
import android.hardware.camera2.CameraManager;
import android.os.Build;
import android.os.IBinder;
import android.util.Log;

import androidx.core.app.NotificationCompat;

public class FlashService extends Service {
    private static final String CHANNEL_ID = "FlashChannel";
    private static final int NOTIFICATION_ID = 1;
    private static final String TAG = "FlashService";

    private CameraManager cameraManager;
    private String cameraId;
    private boolean isFlashOn = false;

    @Override
    public void onCreate() {
        super.onCreate();
        cameraManager = (CameraManager) getSystemService(CAMERA_SERVICE);

        try {
            for (String id : cameraManager.getCameraIdList()) {
                CameraCharacteristics chars = cameraManager.getCameraCharacteristics(id);
                Boolean flashAvailable = chars.get(CameraCharacteristics.FLASH_INFO_AVAILABLE);
                Integer lensFacing = chars.get(CameraCharacteristics.LENS_FACING);
                if (flashAvailable != null && flashAvailable && lensFacing != null && lensFacing == CameraCharacteristics.LENS_FACING_BACK) {
                    cameraId = id;
                    break;
                }
            }
        } catch (Exception e) {
            Log.e(TAG, "Erreur recherche flash", e);
        }

        createNotificationChannel();
        startForeground(NOTIFICATION_ID, buildNotification("Flash prêt (en attente)"));
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        if (cameraId == null) {
            updateNotification("Aucun flash disponible");
            return START_STICKY;
        }

        String action = intent != null ? intent.getAction() : null;

        if ("ON".equals(action) && !isFlashOn) {
            turnFlashOn();
        } else if ("OFF".equals(action) && isFlashOn) {
            turnFlashOff();
        }

        return START_STICKY;
    }

    private void turnFlashOn() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            try {
                cameraManager.setTorchMode(cameraId, true);
                isFlashOn = true;
                updateNotification("Flash allumé 🔦");
            } catch (CameraAccessException e) {
                Log.e(TAG, "Erreur allumage", e);
                updateNotification("Erreur flash");
            }
        }
    }

    private void turnFlashOff() {
        if (isFlashOn && Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            try {
                cameraManager.setTorchMode(cameraId, false);
                isFlashOn = false;
                updateNotification("Flash prêt (en attente)");
            } catch (Exception e) {
                Log.e(TAG, "Erreur extinction", e);
            }
        }
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(CHANNEL_ID, "Contrôle Flash", NotificationManager.IMPORTANCE_LOW);
            getSystemService(NotificationManager.class).createNotificationChannel(channel);
        }
    }

    private Notification buildNotification(String content) {
        return new NotificationCompat.Builder(this, CHANNEL_ID)
                .setContentTitle("Flash à distance")
                .setContentText(content)
                .setSmallIcon(android.R.drawable.ic_dialog_info)
                .setOngoing(true)
                .setPriority(NotificationCompat.PRIORITY_LOW)
                .build();
    }

    private void updateNotification(String content) {
        NotificationManager manager = (NotificationManager) getSystemService(NOTIFICATION_SERVICE);
        manager.notify(NOTIFICATION_ID, buildNotification(content));
    }

    @Override
    public void onDestroy() {
        turnFlashOff();
        super.onDestroy();
    }

    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }
}