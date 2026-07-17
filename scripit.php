<?php
// server-check.php - Verifica se o servidor está pronto

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 Verificação do Servidor</h1>";
echo "<hr>";

$checks = [];

// PHP Version
$checks['PHP Version'] = [
    'status' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'message' => PHP_VERSION . ' ' . (version_compare(PHP_VERSION, '7.4.0', '>=') ? '✅' : '❌ (min 7.4)')
];

// Extensões
$extensions = ['pdo', 'pdo_mysql', 'json', 'curl', 'mbstring', 'zip', 'gd'];
foreach ($extensions as $ext) {
    $checks["Extensão $ext"] = [
        'status' => extension_loaded($ext),
        'message' => extension_loaded($ext) ? '✅' : '❌'
    ];
}

// MySQL
try {
    $conn = @new PDO("mysql:host=localhost;charset=utf8mb4", 'root', '');
    $checks['MySQL'] = ['status' => true, 'message' => '✅ Conectado'];
} catch (Exception $e) {
    $checks['MySQL'] = ['status' => false, 'message' => '❌ ' . $e->getMessage()];
}

// Permissões de escrita
$writeDirs = ['.', 'backend', 'backend/config', 'frontend'];
foreach ($writeDirs as $dir) {
    if (file_exists($dir) || mkdir($dir, 0777, true)) {
        $checks["Permissão $dir"] = [
            'status' => is_writable($dir),
            'message' => is_writable($dir) ? '✅' : '❌'
        ];
    }
}

// Exibir resultados
echo "<table style='width:100%; border-collapse:collapse; font-family:monospace;'>";
foreach ($checks as $name => $check) {
    $color = $check['status'] ? '#2ecc71' : '#e74c3c';
    echo "<tr style='border-bottom:1px solid #eee;'>";
    echo "<td style='padding:8px;'><strong>$name</strong></td>";
    echo "<td style='padding:8px; color:$color;'>{$check['message']}</td>";
    echo "</tr>";
}
echo "</table>";

// Recomendações
echo "<hr>";
echo "<h3>📋 Recomendações</h3>";
echo "<ul>";
if (!$checks['PHP Version']['status']) {
    echo "<li>Atualize o PHP para versão 7.4 ou superior</li>";
}
if (!$checks['Extensão pdo_mysql']['status']) {
    echo "<li>Instale a extensão PDO MySQL</li>";
}
if (!$checks['Extensão curl']['status']) {
    echo "<li>Instale a extensão cURL</li>";
}
echo "<li>Crie um banco de dados MySQL para o sistema</li>";
echo "<li>Execute install/setup.php para concluir a instalação</li>";
echo "</ul>";
?>