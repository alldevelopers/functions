<?php
require_once 'config.php';
require_once 'database.php';

function calcularJurosSimples($valor, $taxaMensal, $meses)
{
    // Juros Simples = Valor * (taxa mensal) * meses (aplicado sobre o valor base ou corrigido)
    $juros = $valor * (($taxaMensal / 100) * $meses);
    $total = $valor + $juros;

    return [
        'juros' => round($juros, 2),
        'total' => round($total, 2),
        'meses' => $meses
    ];
}

function calcularDiferencaMeses($dataInicio, $dataFim)
{
    // Calcula a diferença em dias
    $dt_inicial = new DateTime($dataInicio);
    $dt_final = new DateTime($dataFim);
    $dias = $dt_inicial->diff($dt_final)->days + 1; // +1 para incluir o dia final

    // Converte dias para meses (pela fórmula: dias / 30)
    return $dias / 30;
}

function calcularCorrecaoMonetaria($pdo, $tabela, $dataInicio, $dataFim, $valorBase)
{
    try {
        $stmt = $pdo->prepare("SELECT data, valor FROM `$tabela` WHERE data BETWEEN :inicio AND :fim ORDER BY data ASC");
        $stmt->execute([
            ':inicio' => str_replace('-', '', $dataInicio),
            ':fim' => str_replace('-', '', $dataFim)
        ]);
        $valores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($valores) === 0) return $valorBase;

        $fator = 1;
        foreach ($valores as $indice) {
            $fator *= (1 + floatval($indice['valor']) / 100);
        }

        return $valorBase * $fator;
    } catch (Exception $e) {
        return $valorBase;
    }
}

try {
    $stmt = $pdo->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
          AND table_type = 'BASE TABLE'
        ORDER BY table_name
    ");
    $tabelas = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $tabelas = [];
    $erro = $e->getMessage();
}

$resultado = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valorBase = floatval(str_replace(',', '.', $_POST['valor_base']));
    $taxa = floatval(str_replace(',', '.', $_POST['taxa_juros']));
    $dataInicio = $_POST['data_inicio_juros'];
    $dataFim = $_POST['data_fim_juros'];

    // Calcula o número de meses entre as datas de início e fim dos juros
    $meses = calcularDiferencaMeses($dataInicio, $dataFim);

    $valorCorrigido = $valorBase;
    if (!empty($_POST['aplicar_juros_corrigido']) && !empty($_POST['tabela_indice']) && $_POST['data_inicio_indice'] && $_POST['data_fim_indice']) {
        $valorCorrigido = calcularCorrecaoMonetaria($pdo, $_POST['tabela_indice'], $_POST['data_inicio_indice'], $_POST['data_fim_indice'], $valorBase);
    }

    $resultado = calcularJurosSimples($valorCorrigido, $taxa, $meses);
    $resultado['valor_corrigido'] = round($valorCorrigido, 2);
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Juros Simples Mensal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light p-4">
    <div class="container bg-white p-4 rounded shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Juros</h4>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">Voltar</a>
        </div>
        <p class="text-muted">Calcule juros simples mensal com a fórmula: VALOR DOS JUROS = VALOR CORRIGIDO X ((1 + i ) ^  ( t / 30 ))</p>

        <?php if (!empty($erro)): ?>
            <div class="alert alert-danger">Erro ao buscar índices: <?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label">Valor Base:</label>
                <input type="text" name="valor_base" class="form-control" placeholder="0,00" required value="<?= $_POST['valor_base'] ?? '' ?>">
            </div>

            <h5 class="mt-4">Índices para Correção Monetária</h5>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Índice (Tabela do Banco):</label>
                    <select name="tabela_indice" class="form-select">
                        <option value="">Selecione um índice</option>
                        <?php foreach ($tabelas as $tabela): ?>
                            <option value="<?= $tabela ?>" <?= (isset($_POST['tabela_indice']) && $_POST['tabela_indice'] == $tabela) ? 'selected' : '' ?>><?= strtoupper($tabela) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Data Início:</label>
                    <input type="date" name="data_inicio_indice" class="form-control" value="<?= $_POST['data_inicio_indice'] ?? '' ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Data Fim:</label>
                    <input type="date" name="data_fim_indice" class="form-control" value="<?= $_POST['data_fim_indice'] ?? '' ?>">
                </div>
            </div>

            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" name="aplicar_juros_corrigido" id="jurosCorrigido" <?= isset($_POST['aplicar_juros_corrigido']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="jurosCorrigido">Aplicar Juros sobre o Valor Corrigido</label>
            </div>

            <h5 class="mt-4">Configuração de Juros</h5>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Tipo de Juros:</label>
                    <select name="tipo_juros" class="form-select" disabled>
                        <option selected>Juros Simples (Mensal)</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Taxa de Juros (% ao mês):</label>
                    <input type="text" name="taxa_juros" class="form-control" value="<?= $_POST['taxa_juros'] ?? '1,00' ?>" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Data Início dos Juros:</label>
                    <input type="date" name="data_inicio_juros" class="form-control" value="<?= $_POST['data_inicio_juros'] ?? '' ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Data Fim dos Juros:</label>
                    <input type="date" name="data_fim_juros" class="form-control" value="<?= $_POST['data_fim_juros'] ?? date('Y-m-d') ?>" required>
                </div>
            </div>

            <div class="d-flex justify-content-start gap-2">
                <button type="submit" class="btn btn-success">Calcular</button>
                <button type="reset" class="btn btn-secondary">Limpar</button>
            </div>
        </form>

        <?php if ($resultado): ?>
            <div class="alert alert-info mt-4">
                <p><strong>Valor Corrigido:</strong> R$ <?= number_format($resultado['valor_corrigido'], 2, ',', '.') ?></p>
                <p><strong>Meses:</strong> <?= number_format($resultado['meses'], 5, ',', '.') ?></p>
                <p><strong>Juros Calculado:</strong> R$ <?= number_format($resultado['juros'], 2, ',', '.') ?></p>
                <p><strong>Total com Juros:</strong> R$ <?= number_format($resultado['total'], 2, ',', '.') ?></p>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>