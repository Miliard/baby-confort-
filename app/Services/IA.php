<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * El cable a la inteligencia artificial, sin saber para qué se usa.
 *
 * Acá vive solo el transporte: la clave, el modelo, la temperatura y las
 * diferencias entre los dos proveedores. Quién le pide qué, y con qué
 * instrucciones, es problema de quien la llame.
 *
 * Existe porque el corrector de textos ya tenía todo esto escrito adentro, y
 * al aparecer el segundo uso —leer órdenes— la opción era copiarlo. Copiado,
 * un arreglo hecho de un lado se olvida del otro: fue exactamente lo que pasó
 * con la temperatura, que faltaba y nadie lo vio hasta que la IA empezó a
 * contestar distinto cada vez.
 *
 * (MejorarTexto todavía tiene su propia copia. Conviene moverla acá, pero eso
 * es tocar algo que hoy funciona: se hace aparte, no de paso.)
 */
class IA
{
    public static function disponible(): bool
    {
        return filled(static::clave());
    }

    public static function proveedor(): string
    {
        return config('ia.proveedor', 'openai');
    }

    private static function clave(): ?string
    {
        return config('ia.' . static::proveedor() . '.clave') ?: null;
    }

    private static function modelo(): string
    {
        return (string) config('ia.' . static::proveedor() . '.modelo');
    }

    /**
     * Los modelos que razonan no aceptan que les fijen la temperatura: si se
     * les manda, contestan un error y no hacen nada.
     */
    private static function aceptaTemperatura(): bool
    {
        $m = strtolower(static::modelo());

        foreach (['gpt-5', 'o1', 'o3', 'o4', 'o5'] as $familia) {
            if (str_starts_with($m, $familia)) return false;
        }

        return true;
    }

    /**
     * Una pregunta, una respuesta en texto. Null si no se pudo.
     *
     * Nunca lanza excepciones: quien llama está atendiendo a un cliente y no
     * puede quedarse con la pantalla rota por un problema de red.
     */
    public static function pedir(string $instrucciones, string $entrada, float $temperatura = 0.0): ?string
    {
        if (! static::disponible()) return null;
        if (trim($entrada) === '')  return null;

        try {
            return static::proveedor() === 'openai'
                ? static::conOpenAI($instrucciones, $entrada, $temperatura)
                : static::conAnthropic($instrucciones, $entrada, $temperatura);
        } catch (\Throwable $e) {
            Log::warning('IA: ' . $e->getMessage());
            return null;
        }
    }

    private static function conOpenAI(string $sistema, string $entrada, float $temp): ?string
    {
        $cuerpo = [
            'model'    => static::modelo(),
            'messages' => [
                ['role' => 'system', 'content' => $sistema],
                ['role' => 'user',   'content' => $entrada],
            ],
        ];

        if (static::aceptaTemperatura()) $cuerpo['temperature'] = $temp;

        $r = Http::withToken(static::clave())->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', $cuerpo);

        if (! $r->successful()) {
            Log::warning('IA (openai ' . $r->status() . '): '
                . mb_substr((string) $r->json('error.message', ''), 0, 160));
            return null;
        }

        $t = trim((string) $r->json('choices.0.message.content'));

        return $t !== '' ? $t : null;
    }

    private static function conAnthropic(string $sistema, string $entrada, float $temp): ?string
    {
        $r = Http::withHeaders([
                'x-api-key'         => static::clave(),
                'anthropic-version' => '2023-06-01',
            ])->timeout(30)
            ->post('https://api.anthropic.com/v1/messages', [
                'model'       => static::modelo(),
                'max_tokens'  => 1500,
                'temperature' => $temp,
                'system'      => $sistema,
                'messages'    => [['role' => 'user', 'content' => $entrada]],
            ]);

        if (! $r->successful()) {
            Log::warning('IA (anthropic ' . $r->status() . ')');
            return null;
        }

        $t = trim((string) $r->json('content.0.text'));

        return $t !== '' ? $t : null;
    }

    /**
     * Saca el JSON de una respuesta, aguantando que venga adornado.
     *
     * Aunque se le pida JSON pelado, a veces lo envuelve en ```json ... ``` o
     * le pone una frase delante. Pedirlo mejor no alcanza: hay que aguantarlo.
     */
    public static function json(?string $respuesta): ?array
    {
        $t = trim((string) $respuesta);
        if ($t === '') return null;

        // Lo de adentro del primer { hasta el último }.
        $desde = strpos($t, '{');
        $hasta = strrpos($t, '}');

        if ($desde === false || $hasta === false || $hasta <= $desde) return null;

        $datos = json_decode(substr($t, $desde, $hasta - $desde + 1), true);

        return is_array($datos) ? $datos : null;
    }
}
