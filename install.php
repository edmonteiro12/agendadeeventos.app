<?php
// app_install.php - Script de instalação do aplicativo

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'info';

switch ($action) {
    case 'info':
        echo json_encode([
            'name' => 'Agenda+',
            'version' => '1.0.0',
            'description' => 'Sistema de agenda com controle financeiro',
            'author' => 'Edmonteiro',
            'platforms' => ['web', 'pwa', 'android', 'ios', 'windows', 'mac', 'linux'],
            'download_url' => [
                'windows' => '/download/agenda-setup.exe',
                'mac' => '/download/agenda.dmg',
                'android' => '/download/agenda.apk',
                'linux' => '/download/agenda.AppImage'
            ],
            'install_url' => '/download.html'
        ]);
        break;
        
    case 'generate_apk':
        // Gerar APK (simplificado)
        $zip = new ZipArchive();
        $apkFile = __DIR__ . '/download/agenda.apk';
        
        if ($zip->open($apkFile, ZipArchive::CREATE) === TRUE) {
            // Adicionar arquivos do sistema
            $files = [
                'index.html',
                'cadastrousuario.html',
                'download.html',
                'manifest.json',
                'sw.js'
            ];
            
            foreach ($files as $file) {
                if (file_exists(__DIR__ . '/../' . $file)) {
                    $zip->addFile(__DIR__ . '/../' . $file, $file);
                }
            }
            
            // Adicionar backend
            $backendFiles = glob(__DIR__ . '/../backend/**/*.php');
            foreach ($backendFiles as $file) {
                $relative = str_replace(__DIR__ . '/../', '', $file);
                $zip->addFile($file, $relative);
            }
            
            $zip->close();
            
            echo json_encode([
                'success' => true,
                'message' => 'APK gerado com sucesso',
                'file' => '/download/agenda.apk'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao gerar APK'
            ]);
        }
        break;
        
    case 'install_pwa':
        // Configurar PWA
        $manifestPath = __DIR__ . '/../manifest.json';
        $swPath = __DIR__ . '/../sw.js';
        
        if (file_exists($manifestPath) && file_exists($swPath)) {
            echo json_encode([
                'success' => true,
                'message' => 'PWA configurado com sucesso',
                'manifest' => '/manifest.json',
                'sw' => '/sw.js'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Arquivos PWA não encontrados'
            ]);
        }
        break;
        
    default:
        echo json_encode(['error' => 'Ação inválida']);
}
?>