<?php
/**
 * BaseController
 *
 * Clase base para los controladores de los formularios refactorizados desde
 * Visual FoxPro (carpeta /scx). Provee el ruteo por "action" y utilidades
 * comunes de respuesta.
 *
 * IMPORTANTE: este proyecto solo contiene la estructura de formularios y
 * controladores. La logica de negocio (conexion a SQL Server, stored
 * procedures sp_*, etc.) NO esta implementada: cada accion queda como stub.
 */
abstract class BaseController
{
    /**
     * Despacha la peticion HTTP hacia el metodo indicado por el parametro
     * "action" (?action=guardar -> $this->guardar()).
     */
    public function handle(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $action = $_REQUEST['action'] ?? 'index';

        // Solo se permiten metodos publicos declarados explicitamente.
        if (!method_exists($this, $action) || in_array($action, ['handle', 'json', 'param', 'stub', 'usuario', '__construct'], true)) {
            $this->json(['ok' => false, 'msg' => "Accion no valida: {$action}"], 400);
            return;
        }

        $this->{$action}();
    }

    /** idusuario del usuario logueado (operadora), o '' si no hay sesion. */
    protected function usuario(): string
    {
        return (string) ($_SESSION['idusuario'] ?? '');
    }

    /** Devuelve una respuesta JSON y corta la ejecucion. */
    protected function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        // El texto de SQL Server (ODBC) viene en Windows-1252; json_encode exige
        // UTF-8 (si no, devuelve false y no imprime nada).
        $data = $this->utf8ize($data);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    /** Convierte recursivamente strings Windows-1252 a UTF-8 (deja intactos los ya UTF-8). */
    private function utf8ize($d)
    {
        if (is_array($d)) {
            foreach ($d as $k => $v) {
                $d[$k] = $this->utf8ize($v);
            }
            return $d;
        }
        if (is_string($d) && !mb_check_encoding($d, 'UTF-8')) {
            return mb_convert_encoding($d, 'UTF-8', 'Windows-1252');
        }
        return $d;
    }

    /** Atajo para leer un parametro del request con valor por defecto. */
    protected function param(string $name, $default = null)
    {
        return $_REQUEST[$name] ?? $default;
    }

    /** Respuesta estandar para acciones aun no implementadas. */
    protected function stub(string $accion): void
    {
        $this->json([
            'ok'     => false,
            'accion' => $accion,
            'msg'    => "Stub: la logica de '{$accion}' no esta implementada.",
        ]);
    }
}
