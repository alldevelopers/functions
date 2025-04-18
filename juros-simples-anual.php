<?php
require_once 'config.php';
require_once 'database.php';

/**
 * Calcula Juros com base no modelo exato mostrado na imagem.
 * 
 * A fórmula identificada é:
 * 1. Calcular meses = dias / 30
 * 2. Calcular meses para juros = meses / 12
 * 3. Juros = Principal * (Percentual/100) * (Meses/12)
 */
function calcularJurosSimples($valor, $percentualJuros, $dias, $diasBase = 30, $dividirPor = 12)
{
    // Cálculo da quantidade de meses
    $qtdeMeses = $dias / $diasBase;

    // Cálculo da quantidade de meses para o cálculo (conforme mostrado na imagem)
    $qtdeMesesCalculo = $qtdeMeses / $dividirPor;

    // Cálculo dos juros (exatamente como mostrado na imagem)
    $juros = $valor * ($percentualJuros / 100) * $qtdeMesesCalculo;

    // Total (principal + juros)
    $total = $valor + $juros;

    return [
        'dias' => $dias,
        'dias_base' => $diasBase,
        'qtde_meses' => $qtdeMeses,
        'dividir_por' => $dividirPor,
        'qtde_meses_calculo' => $qtdeMesesCalculo,
        'percentual_juros' => $percentualJuros,
        'principal' => $valor,
        'juros' => round($juros, 2),
        'total' => round($total, 2)
    ];
}

/**
 * Calcula a diferença em dias entre duas datas,
 * +1 para incluir o dia final.
 */
function calcularDiferencaDias($dataInicio, $dataFim)
{
    if (empty($dataInicio) || empty($dataFim)) {
        return 0; // Retorna zero se as datas não forem válidas
    }

    $dt_inicial = new DateTime($dataInicio);
    $dt_final = new DateTime($dataFim);
    return $dt_inicial->diff($dt_final)->days + 1;
}

/**
 * Calcula a correção monetária acumulada a partir de índices do banco.
 */
function calcularCorrecaoMonetaria($pdo, $tabela, $dataInicio, $dataFim, $valorBase)
{
    if (empty($dataInicio) || empty($dataFim) || empty($tabela)) {
        return $valorBase; // Retorna o valor base se os parâmetros não forem válidos
    }

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
$dividirPor = 12; // Divisor conforme a imagem

// Processa o formulário (POST).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtém valores do formulário com validação para evitar warnings
    $valorBase = isset($_POST['valor_base']) ?
        floatval(str_replace(',', '.', $_POST['valor_base'])) : 0;

    $taxaAnual = isset($_POST['taxa_juros']) ?
        floatval(str_replace(',', '.', $_POST['taxa_juros'])) : 0;

    $dataInicio = $_POST['data_inicio_juros'] ?? '';
    $dataFim = $_POST['data_fim_juros'] ?? '';

    // Só calcula se os dados essenciais estiverem presentes
    if (!empty($dataInicio) && !empty($dataFim) && $valorBase > 0 && $taxaAnual > 0) {
        // Calcula o número de dias entre as datas
        $dias = calcularDiferencaDias($dataInicio, $dataFim);

        // Verifica se aplica correção monetária
        $valorCorrigido = $valorBase;
        if (
            isset($_POST['aplicar_juros_corrigido']) &&
            !empty($_POST['tabela_indice']) &&
            !empty($_POST['data_inicio_indice']) &&
            !empty($_POST['data_fim_indice'])
        ) {
            $valorCorrigido = calcularCorrecaoMonetaria(
                $pdo,
                $_POST['tabela_indice'],
                $_POST['data_inicio_indice'],
                $_POST['data_fim_indice'],
                $valorBase
            );
        }

        // Calcula os juros conforme o modelo
        $resultado = calcularJurosSimples($valorCorrigido, $taxaAnual, $dias, $diasBase, $dividirPor);
    }
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

        <?php if (!empty($erro)): ?>
            <div class="alert alert-danger">Erro ao buscar índices: <?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label">Valor Base:</label>
                <input type="text" name="valor_base" class="form-control" placeholder="0,00" required
                    value="<?= isset($_POST['valor_base']) ? htmlspecialchars($_POST['valor_base']) : '' ?>">
            </div>

            <h5 class="mt-4">Índices para Correção Monetária</h5>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Índice (Tabela do Banco):</label>
                    <select name="tabela_indice" class="form-select">
                        <option value="">Selecione um índice</option>
                        <?php foreach ($tabelas as $tabela): ?>
                            <option value="<?= htmlspecialchars($tabela) ?>"
                                <?= (isset($_POST['tabela_indice']) && $_POST['tabela_indice'] == $tabela) ? 'selected' : '' ?>>
                                <?= htmlspecialchars(strtoupper($tabela)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Data Início:</label>
                    <input type="date" name="data_inicio_indice" class="form-control"
                        value="<?= isset($_POST['data_inicio_indice']) ? htmlspecialchars($_POST['data_inicio_indice']) : '' ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Data Fim:</label>
                    <input type="date" name="data_fim_indice" class="form-control"
                        value="<?= isset($_POST['data_fim_indice']) ? htmlspecialchars($_POST['data_fim_indice']) : '' ?>">
                </div>
            </div>

            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" name="aplicar_juros_corrigido" id="jurosCorrigido"
                    <?= isset($_POST['aplicar_juros_corrigido']) ? 'checked' : '' ?>>
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
                    <input type="text" name="taxa_juros" class="form-control"
                        value="<?= isset($_POST['taxa_juros']) ? htmlspecialchars($_POST['taxa_juros']) : '' ?>" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Data Início dos Juros:</label>
                    <input type="date" name="data_inicio_juros" class="form-control"
                        value="<?= isset($_POST['data_inicio_juros']) ? htmlspecialchars($_POST['data_inicio_juros']) : '' ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Data Fim dos Juros:</label>
                    <input type="date" name="data_fim_juros" class="form-control"
                        value="<?= isset($_POST['data_fim_juros']) ? htmlspecialchars($_POST['data_fim_juros']) : date('Y-m-d') ?>" required>
                </div>
            </div>

            <div class="d-flex justify-content-start gap-2">
                <button type="submit" class="btn btn-success">Calcular</button>
                <button type="reset" class="btn btn-secondary">Limpar</button>
            </div>
        </form>

        <?php if ($resultado): ?>
            <div class="mt-4">
                <h5 class="alert-heading">JUROS SIMPLES ANUAL</h5>
                <p><strong>PRINCIPAL:</strong> R$ <?= number_format($resultado['principal'], 2, ',', '.') ?></p>

                <h5 class="mt-3">JUROS</h5>
                <p><strong>DATA INICIAL:</strong> <?= date('d/m/Y', strtotime($_POST['data_inicio_juros'])) ?></p>
                <p><strong>DATA FINAL:</strong> <?= date('d/m/Y', strtotime($_POST['data_fim_juros'])) ?></p>
                <p><strong>QTDE DIAS:</strong> <?= number_format($resultado['dias'], 2, ',', '.') ?></p>
                <p><strong>DIAS BASE:</strong> <?= number_format($resultado['dias_base'], 2, ',', '.') ?></p>
                <p><strong>PERCENTUAL JUROS ANUAIS:</strong> <?= number_format($resultado['percentual_juros'], 2, ',', '.') ?>%</p>
                <p><strong>QTDE MESES:</strong> <?= number_format($resultado['qtde_meses'], 2, ',', '.') ?></p>
                <p><strong>DIVIDIR POR:</strong> <?= $resultado['dividir_por'] ?></p>
                <p><strong>QTDE MESES:</strong> <?= number_format($resultado['qtde_meses_calculo'], 7, ',', '.') ?></p>

                <h5 class="mt-3">RESUMO FINAL</h5>
                <p><strong>VALOR CORRIGIDO:</strong> R$ <?= number_format($resultado['principal'], 2, ',', '.') ?></p>
                <p class="bg-yellow"><strong>VALOR DOS JUROS:</strong> R$ <?= number_format($resultado['juros'], 2, ',', '.') ?></p>
                <p class="bg-yellow"><strong>TOTAL ATUALIZADO:</strong> R$ <?= number_format($resultado['total'], 2, ',', '.') ?></p>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>