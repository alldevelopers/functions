<?php
if (!function_exists('buscarRegistrosProximos')) {
    /**
     * Busca registros próximos quando não encontrados no intervalo exato.
     *
     * @param PDO    $pdo         Conexão com o banco de dados.
     * @param string $indice      Código/nome do índice.
     * @param string $data_inicial Data inicial formatada.
     * @param string $data_final  Data final formatada.
     * @return array Registros encontrados.
     */
    function buscarRegistrosProximos($pdo, $indice, $data_inicial, $data_final)
    {
        $registros = [];

        try {
            // Busca o registro anterior à data inicial
            $stmt = $pdo->prepare("
               SELECT data, valor 
               FROM `$indice` 
               WHERE data <= :data_inicial
               ORDER BY data DESC 
               LIMIT 1
           ");
            $stmt->execute([':data_inicial' => $data_inicial]);
            $registro_anterior = $stmt->fetch(PDO::FETCH_ASSOC);

            // Busca o registro posterior à data final
            $stmt = $pdo->prepare("
               SELECT data, valor 
               FROM `$indice` 
               WHERE data >= :data_final
               ORDER BY data ASC 
               LIMIT 1
           ");
            $stmt->execute([':data_final' => $data_final]);
            $registro_posterior = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($registro_anterior) {
                $registros[] = $registro_anterior;
            }
            if ($registro_posterior && (!$registro_anterior || $registro_posterior['data'] != $registro_anterior['data'])) {
                $registros[] = $registro_posterior;
            }

            // Se não houver registros, tenta buscar o último registro disponível
            if (empty($registros)) {
                $stmt = $pdo->query("SELECT data, valor FROM `$indice` ORDER BY data DESC LIMIT 1");
                $ultimo_registro = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($ultimo_registro) {
                    $registros[] = $ultimo_registro;
                }
            }
        } catch (PDOException $e) {
            error_log("Erro ao buscar registros próximos para o índice {$indice}: " . $e->getMessage());
        }

        return $registros;
    }
}
