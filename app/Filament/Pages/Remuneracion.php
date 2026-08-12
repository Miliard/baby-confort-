<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\RemuneracionSheet;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Cuánto hay que remunerar por los paquetes de AIWIBI.
 * Lee la hoja de entregas publicada en Google Sheets, así se actualiza sola.
 */
class Remuneracion extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Remuneración';
    protected static ?string $title           = 'Remuneración AIWIBI';
    protected static ?int    $navigationSort  = 2;

    protected static string $view = 'filament.pages.remuneracion';

    public const CLAVE_URL      = 'sheet_remuneracion_url';
    public const CLAVE_COMISION = 'remun_comision';
    public const CLAVE_ENVIO    = 'remun_por_envio';

    public string $desde = '';
    public string $hasta = '';
    public string $url   = '';

    /** Se pueden cambiar aquí mismo y quedan guardados. */
    public float $comision = RemuneracionSheet::COMISION;
    public float $porEnvio = RemuneracionSheet::POR_ENVIO;

    public function mount(): void
    {
        $this->url      = (string) Setting::get(self::CLAVE_URL, '');
        $this->comision = (float) Setting::get(self::CLAVE_COMISION, RemuneracionSheet::COMISION);
        $this->porEnvio = (float) Setting::get(self::CLAVE_ENVIO, RemuneracionSheet::POR_ENVIO);
        $this->desde    = now()->startOfMonth()->toDateString();
        $this->hasta    = now()->toDateString();
    }

    /** Guarda los valores en cuanto se cambian, para no perderlos. */
    public function updatedComision($v): void
    {
        Setting::put(self::CLAVE_COMISION, (float) $v);
    }

    public function updatedPorEnvio($v): void
    {
        Setting::put(self::CLAVE_ENVIO, (float) $v);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('conectar')
                ->label('Conectar la hoja')
                ->icon('heroicon-o-link')
                ->color('gray')
                ->modalHeading('Conectar tu hoja de Google Sheets')
                ->modalDescription('En Google Sheets: Archivo → Compartir → Publicar en la Web → elegí la hoja → formato CSV → Publicar. Pegá aquí el enlace que te da.')
                ->form([
                    \Filament\Forms\Components\TextInput::make('url')
                        ->label('Enlace CSV publicado')
                        ->placeholder('https://docs.google.com/spreadsheets/d/e/.../pub?gid=0&single=true&output=csv')
                        ->default(fn () => Setting::get(self::CLAVE_URL, ''))
                        ->url()
                        ->required(),
                ])
                ->action(function (array $data) {
                    Setting::put(self::CLAVE_URL, trim($data['url']));
                    $this->url = trim($data['url']);
                    RemuneracionSheet::filas($this->url, true);
                    Notification::make()->title('Hoja conectada')->success()->send();
                }),

            Action::make('actualizar')
                ->label('Actualizar ahora')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    if ($this->url === '') {
                        Notification::make()->title('Primero conectá la hoja')->warning()->send();
                        return;
                    }
                    $filas = RemuneracionSheet::filas($this->url, true);
                    Notification::make()
                        ->title(count($filas) . ' entregas leídas')
                        ->success()->send();
                }),
        ];
    }

    /** Atajos de periodo. */
    public function periodo(string $cual): void
    {
        $hoy = now();
        [$this->desde, $this->hasta] = match ($cual) {
            'mes'     => [$hoy->copy()->startOfMonth()->toDateString(), $hoy->toDateString()],
            'anterior'=> [$hoy->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                          $hoy->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            'semana'  => [$hoy->copy()->startOfWeek()->toDateString(), $hoy->toDateString()],
            default   => ['', ''],
        };
    }

    /** Texto tipo "hace 2 minutos", para saber que el tablero está vivo. */
    public function getLeidoProperty(): ?string
    {
        if ($this->url === '') return null;

        $at = RemuneracionSheet::leidoEn($this->url);
        return $at ? $at->diffForHumans() : null;
    }

    public function getResumenProperty(): array
    {
        $filas = $this->url === '' ? [] : RemuneracionSheet::filas($this->url);

        return RemuneracionSheet::calcular(
            $filas,
            $this->desde !== '' ? $this->desde : null,
            $this->hasta !== '' ? $this->hasta : null,
            $this->comision,
            $this->porEnvio,
        );
    }
}
