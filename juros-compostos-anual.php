<?php
require_once 'config.php';
require_once 'database.php';

/**
 * Calcula Juros Compostos Anual de forma dinâmica.
 * 
 * Aplica a fórmula: (JUROS/100+1) ^ (QTDE MESES/12)
 * O expoente é calculado dinamicamente como QTDE MESES/12
 */
function calcularJurosCompostosAnual($valor, $taxaAnual, $dias, $diasBase = 30)
{
    // Taxa em formato decimal
    $taxaDecimal = $taxaAnual / 100;

    // Calculamos a quantidade de meses
    $qtdeMeses = $dias / $diasBase;

    // Calculamos a quantidade anual (referência para exibição)
    $qtdeAnual = 12; // Fixo em 12 para representar os 12 meses do ano

    // Calculamos o expoente para a fórmula (qtdeMeses/12)
    $expoente = $qtdeMeses / 12;

    // Calculamos o fator de juros: (1 + taxa)^(expoente)
    $fator = pow((1 + $taxaDecimal), $expoente);

    // Calculamos o montante: Principal * Fator
    $montante = $valor * $fator;

    // Calculamos os juros
    $juros = $montante - $valor;

    // Retorno da função com todos os detalhes para exibição
    return [
        'dias' => $dias,
        'dias_base' => $diasBase,
        'qtde_meses' => $qtdeMeses,
        'qtde_anual' => $qtdeAnual,
        'expoente' => $expoente,  // Expoente da fórmula (qtdeMeses/12)
        'taxa_anual' => $taxaAnual,
        'valor_principal' => $valor,
        'fator_1' => (1 + $taxaDecimal),  // Valor da 1a. parte da fórmula
        'fator_juros' => $fator,
        'juros' => round($juros, 2),
        'total' => round($montante, 2)
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
$diasBase = 30; // Dias base

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

        // Calcula os juros compostos anuais
        $resultado = calcularJurosCompostosAnual($valorCorrigido, $taxaAnual, $dias, $diasBase);
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Juros Compostos Anual</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .bg-yellow {
            background-color: yellow;
        }

        .bg-cyan {
            background-color: cyan;
        }
    </style>
</head>

<body class="bg-light p-4">
    <div class="container bg-white p-4 rounded shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">JUROS ANUAL COMPOSTOS</h4>
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
                        <option selected>Juros Anual Compostos</option>
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
                <h5>JUROS ANUAL COMPOSTOS</h5>
                <p><strong>VALOR:</strong> R$ <?= number_format($resultado['valor_principal'], 2, ',', '.') ?></p>

                <h5 class="mt-3">DETALHES</h5>
                <p><strong>INICIO:</strong> <?= date('d/m/Y', strtotime($_POST['data_inicio_juros'])) ?></p>
                <p><strong>FINAL:</strong> <?= date('d/m/Y', strtotime($_POST['data_fim_juros'])) ?></p>
                <p><strong>QTDE DIAS:</strong> <?= number_format($resultado['dias'], 2, ',', '.') ?></p>
                <p><strong>MESES:</strong> <?= number_format($resultado['dias_base'], 0, ',', '.') ?></p>
                <p><strong>QTDE MESES:</strong> <?= number_format($resultado['qtde_meses'], 2, ',', '.') ?></p>
                <p><strong>JUROS:</strong> <?= number_format($resultado['taxa_anual'], 2, ',', '.') ?></p>
                <p><strong>TOTAL JUROS:</strong> <?= number_format($resultado['qtde_meses'], 2, ',', '.') ?></p>
                <p><strong>ANUAL:</strong> <?= number_format($resultado['qtde_anual'], 0, ',', '.') ?></p>

                <h5 class="mt-3 bg-yellow">FÓRMULA</h5>
                <p><strong>FÓRMULA:</strong> (JUROS/100+1) ^ (<?= number_format($resultado['expoente'], 6, ',', '.') ?>)</p>
                <p><strong>VALOR DA 1A. PARTE FÓRMULA:</strong> (<?= number_format($resultado['taxa_anual'], 2, ',', '.') ?> / 100 + 1 = <?= number_format($resultado['fator_1'], 4, ',', '.') ?>)</p>
                <p><strong>COMO FICA A FÓRMULA:</strong> <?= number_format($resultado['fator_1'], 4, ',', '.') ?> ^ <?= number_format($resultado['expoente'], 6, ',', '.') ?></p>

                <h5 class="mt-3">RESUMO FINAL</h5>
                <p><strong>RESULTADO:</strong> <?= number_format($resultado['fator_juros'], 8, ',', '.') ?> DE JUROS CAPITALIZADOS</p>
                <p class="bg-yellow"><strong>VALOR DO JUROS:</strong> R$ <?= number_format($resultado['juros'], 2, ',', '.') ?></p>
                <p class="bg-yellow"><strong>VALOR TOTAL:</strong> R$ <?= number_format($resultado['total'], 2, ',', '.') ?></p>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>