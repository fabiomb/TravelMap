<?php
/**
 * MCP Dispatcher
 *
 * Registra tools, enruta tools/call y construye el catálogo para tools/list.
 * Cada tool es un array con:
 *   - name: string
 *   - description: string
 *   - inputSchema: array (JSON Schema)
 *   - handler: callable($params): array
 */

final class Dispatcher
{
    /** @var array<string, array> name → tool descriptor */
    private array $tools = [];

    public function register(string $name, string $description, array $inputSchema, callable $handler): void
    {
        $this->tools[$name] = [
            'name'        => $name,
            'description' => $description,
            'inputSchema' => $inputSchema,
            'handler'     => $handler,
        ];
    }

    /** Responde a tools/list */
    public function toolsList(): array
    {
        $list = [];
        foreach ($this->tools as $tool) {
            $list[] = [
                'name'        => $tool['name'],
                'description' => $tool['description'],
                'inputSchema' => $tool['inputSchema'],
            ];
        }
        return ['tools' => $list];
    }

    /**
     * Despacha tools/call.
     * Retorna array {content: [{type,text}], isError: bool}
     * o lanza ToolException para errores de dominio.
     */
    public function toolsCall(string $name, array $params, string $requestId = ''): array
    {
        if (!isset($this->tools[$name])) {
            throw new ToolException("Tool desconocido: {$name}", 'TOOL_NOT_FOUND', -32601);
        }

        $tool = $this->tools[$name];

        // Validar input contra JSON Schema
        $fieldErrors = Schema::validate($tool['inputSchema'], $params);
        if (!empty($fieldErrors)) {
            throw new ToolException(
                'Parámetros de entrada inválidos',
                'INVALID_INPUT',
                -32602,
                ['fieldErrors' => $fieldErrors]
            );
        }

        $t0     = microtime(true);
        $result = ($tool['handler'])($params);
        $ms     = (microtime(true) - $t0) * 1000;

        McpLogger::toolResult($name, $result, $ms, $requestId);

        return [
            'content' => [[
                'type' => 'text',
                'text' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            ]],
            'isError' => false,
        ];
    }
}

/**
 * Excepción de dominio para errores en tools.
 * El servidor la convierte en respuesta JSON-RPC estructurada.
 */
class ToolException extends RuntimeException
{
    public string $tag;
    public int $rpcCode;
    public ?array $extra;

    public function __construct(string $message, string $tag = 'SERVER_ERROR', int $rpcCode = -32000, ?array $extra = null)
    {
        parent::__construct($message);
        $this->tag     = $tag;
        $this->rpcCode = $rpcCode;
        $this->extra   = $extra;
    }
}
