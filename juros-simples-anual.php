<?php
require_once 'config.php';
require_once 'database.php';

/**
 * Calcula Juros Simples Anual de forma completamente dinâmica,
 * usando apenas os valores fornecidos pelo usuário.
 */
function calcularJurosSimples($valor, $taxaAnual, $dias, $diasBase = 30)
{
    // Cálculo de meses para exibição
    $meses = $dias / $diasBase;

    // Cálculo do percentual - exatamente como mostrado na imagem
    $percentualAplicado = $taxaAnual * ($dias / 360);

    // Cálculo do juros - usamos apenas os parâmetros da função
    // O cálculo é baseado nos parâmetros de entrada e os valores padrão
    // do mercado financeiro para anos comerciais (360 dias)
    $juros = $valor * ($taxaAnual / 100) * ($dias / 360);
    $total = $valor + $juros;

    return [
        'percentual_aplicado' => $percentualAplicado,
        'juros' => round($juros, 2),
        'total' => round($total, 2),
        'meses' => $meses,
        'dias' => $dias,
        'dias_base' => $diasBase
    ];
}

/**
 * Calcula a diferença em dias entre duas datas,
 * +1 para incluir o dia final.
 */
function calcularDiferencaDias($dataInicio, $dataFim)
{
    $dt_inicial = new DateTime($dataInicio);
    $dt_final = new DateTime($dataFim);
    return $dt_inicial->diff($dt_final)->days + 1;
}

/**
 * Calcula a correção monetária acumulada a partir de índices do banco.
 */
function calcularCorrecaoMonetaria($pdo, $tabela, $dataInicio, $dataFim, $valorBase)
{
    try {
        $stmt = $pdo->prepare("SELECT data, valor FROM `$tabela` WHERE data BETWEEN :inicio AND :fim ORDER BY data ASC");
        $stmt->execute([
            ':inicio' => str_replace('-', '', $dataInicio),
            ':fim'    => str_replace('-', '', $dataFim)
        ]);
        $valores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($valores) === 0) {
            return $valorBase;
        }

        $fator = 1;
        foreach ($valores as $indice) {
            $fator *= (1 + floatval($indice['valor']) / 100);
        }

        return $valorBase * $fator;
    } catch (Exception $e) {
        return $valorBase;
    }
}

// Recupera a lista de tabelas do banco de dados (para os índices).
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
$diasBase = 30; // Dias base conforme a imagem

// Processa o formulário (POST).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valorBase  = floatval(str_replace(',', '.', $_POST['valor_base']));
    $taxaAnual  = floatval(str_replace(',', '.', $_POST['taxa_juros']));
    $dataInicio = $_POST['data_inicio_juros'];
    $dataFim    = $_POST['data_fim_juros'];

    // Calcula o número de dias entre as datas
    $dias = calcularDiferencaDias($dataInicio, $dataFim);

    // Verifica se aplica correção monetária
    $valorCorrigido = $valorBase;
    if (
        !empty($_POST['aplicar_juros_corrigido'])
        && !empty($_POST['tabela_indice'])
        && !empty($_POST['data_inicio_indice'])
        && !empty($_POST['data_fim_indice'])
    ) {
        $valorCorrigido = calcularCorrecaoMonetaria(
            $pdo,
            $_POST['tabela_indice'],
            $_POST['data_inicio_indice'],
            $_POST['data_fim_indice'],
            $valorBase
        );
    }

    // Calcula os juros simples anuais
    $resultado = calcularJurosSimples($valorCorrigido, $taxaAnual, $dias, $diasBase);

    // Adicionamos ao resultado alguns campos extras para exibição:
    $resultado['valor_corrigido'] = round($valorCorrigido, 2);
    $resultado['taxa_anual'] = $taxaAnual;
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Juros Simples Anual</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .bg-yellow {
            background-color: yellow;
        }
    </style>
</head>

<body class="bg-light p-4">
    <div class="container bg-white p-4 rounded shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">JUROS SIMPLES ANUAL</h4>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">Voltar</a>
        </div>

        <p class="text-muted">
            Calculadora de juros simples anuais utilizando a fórmula:<br>
            <strong>Juros = Principal × Taxa × (Dias / 360)</strong><br>
            onde 360 é o ano comercial padrão em cálculos financeiros.
        </p>

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
                            <option value="<?= $tabela ?>" <?= (isset($_POST['tabela_indice']) && $_POST['tabela_indice'] == $tabela) ? 'selected' : '' ?>>
                                <?= strtoupper($tabela) ?>
                            </option>
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
                        <option selected>Juros Simples Anual</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Taxa de Juros Anual (%):</label>
                    <input type="text" name="taxa_juros" class="form-control" value="<?= $_POST['taxa_juros'] ?? '' ?>" required>
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
            <div class="mt-4">
                <h5>JUROS SIMPLES ANUAL</h5>
                <p><strong>PRINCIPAL:</strong> R$ <?= number_format($resultado['valor_corrigido'], 2, ',', '.') ?></p>

                <h5 class="mt-3">JUROS</h5>
                <p><strong>DATA INICIAL:</strong> <?= date('d/m/Y', strtotime($_POST['data_inicio_juros'])) ?></p>
                <p><strong>DATA FINAL:</strong> <?= date('d/m/Y', strtotime($_POST['data_fim_juros'])) ?></p>
                <p><strong>QTDA DIAS:</strong> <?= number_format($resultado['dias'], 2, ',', '.') ?></p>
                <p><strong>DIAS BASE:</strong> <?= number_format($resultado['dias_base'], 2, ',', '.') ?></p>
                <p><strong>PERCENTUAL JUROS ANUAIS:</strong> <?= number_format($resultado['taxa_anual'], 5, ',', '') ?></p>

                <h5 class="mt-3">RESUMO FINAL</h5>
                <p><strong>VALOR CORRIGIDO:</strong> R$ <?= number_format($resultado['valor_corrigido'], 2, ',', '.') ?></p>
                <p class="bg-yellow"><strong>PERCENTUAL JUROS:</strong> <?= number_format($resultado['percentual_aplicado'], 11, ',', '') ?></p>
                <p class="bg-yellow"><strong>VALOR DOS JUROS:</strong> <?= number_format($resultado['juros'], 2, ',', '.') ?></p>
                <p class="bg-yellow"><strong>TOTAL ATUALIZADO:</strong> R$ <?= number_format($resultado['total'], 2, ',', '.') ?></p>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>