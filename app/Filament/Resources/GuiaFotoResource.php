<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GuiaFotoResource\Pages;
use App\Models\GuiaFoto;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

/**
 * Listado de las fotos de paquetes ya guardadas, con la tabla nativa de Filament
 * (buscador, orden y paginación). Desde aquí se envía el seguimiento al cliente.
 */
class GuiaFotoResource extends Resource
{
    protected static ?string $model = GuiaFoto::class;
    protected static ?string $navigationIcon = 'heroicon-o-photo';
    protected static ?string $navigationLabel = 'Guías con foto';
    protected static ?string $modelLabel = 'Guía con foto';
    protected static ?string $pluralModelLabel = 'Guías con foto';
    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('guia')->label('Guía')
                    ->weight('bold')->searchable()
                    ->copyable()->copyMessage('✓ Guía copiada')
                    ->icon('heroicon-m-clipboard-document')->iconPosition('after')
                    ->tooltip('Clic para copiar la guía'),

                // Un solo clic copia la guía y su enlace juntos, listo para pegar.
                Tables\Columns\TextColumn::make('enlace')->label('Copiar')
                    ->getStateUsing(fn (GuiaFoto $record) => 'Guía ' . $record->guia . "\n" . $record->enlaceRastreo())
                    ->formatStateUsing(fn () => '📋 Guía + enlace')
                    ->copyable()->copyMessage('✓ Copiado: guía y enlace')
                    ->color('primary')->weight('bold')
                    ->tooltip('Copia la guía y el enlace de seguimiento juntos'),

                Tables\Columns\TextColumn::make('created_at')->label('Fecha')
                    ->dateTime('d/m/Y H:i')->sortable(),

                // Se pueden escribir a mano cuando el OCR no los leyó (y así buscar por ellos).
                // Busca por parte del nombre, sin importar mayúsculas ni acentos.
                Tables\Columns\TextInputColumn::make('nombre')->label('Cliente')
                    ->placeholder('Escribir nombre')->rules(['max:80'])
                    ->searchable(query: function ($query, string $search) {
                        $s = mb_strtolower(trim($search));
                        $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
                        if ($s === '') return $query;

                        // Quita acentos también de lo guardado, para que coincida igual.
                        $campo = "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(nombre,''),"
                               . "'á','a'),'é','e'),'í','i'),'ó','o'),'ú','u'),'ñ','n'))";

                        return $query->whereRaw("{$campo} LIKE ?", ['%' . $s . '%']);
                    }),

                // Botón aparte para copiar el teléfono (el campo de al lado es editable).
                Tables\Columns\TextColumn::make('tel_copiar')->label('')
                    ->getStateUsing(fn (GuiaFoto $record) => $record->telefono ?: null)
                    ->formatStateUsing(fn ($state) => $state ? '📞 Copiar' : '')
                    ->copyable(fn ($state) => filled($state))
                    ->copyMessage('✓ Teléfono copiado')
                    ->color('success')->weight('bold')
                    ->tooltip('Copiar el teléfono del cliente'),

                // Busca aunque el número se escriba con guion, espacio o de corrido.
                Tables\Columns\TextInputColumn::make('telefono')->label('Teléfono')
                    ->placeholder('Escribir teléfono')->rules(['max:30'])
                    ->searchable(query: function ($query, string $search) {
                        $d = preg_replace('/\D/', '', $search);
                        if ($d === '') return $query;

                        // Número guardado sin espacios ni guiones.
                        $campo = "REPLACE(REPLACE(REPLACE(COALESCE(telefono,''),' ',''),'-',''),'+','')";

                        // Búsqueda tolerante: si el OCR leyó mal un dígito, igual encuentra.
                        // Se parte lo buscado en pedazos de 4 y basta que uno coincida.
                        $trozos = [];
                        $largo  = strlen($d);
                        if ($largo <= 4) {
                            $trozos[] = $d;
                        } else {
                            for ($i = 0; $i + 4 <= $largo; $i++) {
                                $trozos[] = substr($d, $i, 4);
                            }
                        }

                        return $query->where(function ($q) use ($campo, $trozos) {
                            foreach ($trozos as $t) {
                                $q->orWhereRaw("{$campo} LIKE ?", ['%' . $t . '%']);
                            }
                        });
                    }),

                Tables\Columns\ImageColumn::make('ruta')->label('Foto')
                    ->disk('public')->height(42)->square()
                    ->action(
                        Tables\Actions\Action::make('abrir_foto')
                            ->modalHeading(fn (GuiaFoto $record) => 'Etiqueta de la guía ' . $record->guia)
                            ->modalContent(fn (GuiaFoto $record) => new \Illuminate\Support\HtmlString(
                                '<img src="' . e($record->url()) . '" alt="Etiqueta" style="width:100%;border-radius:10px;display:block">'
                            ))
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Cerrar')
                            ->modalWidth('lg')
                    )
                    ->tooltip('Clic para ver la etiqueta en grande'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('cuando')
                    ->label('Cuándo se subió')
                    ->options([
                        'hoy'    => 'Hoy',
                        'ayer'   => 'Ayer',
                        'semana' => 'Últimos 7 días',
                    ])
                    ->query(function ($query, array $data) {
                        return match ($data['value'] ?? null) {
                            'hoy'    => $query->whereDate('created_at', today()),
                            'ayer'   => $query->whereDate('created_at', today()->subDay()),
                            'semana' => $query->where('created_at', '>=', now()->subDays(7)),
                            default  => $query,
                        };
                    }),

                Tables\Filters\Filter::make('sin_telefono')
                    ->label('Solo las que les falta el teléfono')
                    ->query(fn ($query) => $query->where(fn ($q) => $q->whereNull('telefono')->orWhere('telefono', ''))),
            ])
            ->actions([
                // Abre la etiqueta en grande para revisar que la guía y el teléfono estén bien.
                Tables\Actions\Action::make('ver_foto')
                    ->label('Ver foto')->icon('heroicon-o-photo')->color('gray')
                    ->modalHeading(fn (GuiaFoto $record) => 'Etiqueta de la guía ' . $record->guia)
                    ->modalContent(fn (GuiaFoto $record) => new \Illuminate\Support\HtmlString(
                        '<img src="' . e($record->url()) . '" alt="Etiqueta" '
                        . 'style="width:100%;border-radius:10px;display:block">'
                        . '<div style="margin-top:10px;font-size:13px;color:#6b7280;text-align:center">'
                        . 'Guía <b>' . e($record->guia) . '</b>'
                        . ($record->telefono ? ' · Tel <b>' . e($record->telefono) . '</b>' : '')
                        . ($record->nombre ? ' · ' . e($record->nombre) : '')
                        . '</div>'
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('lg'),

                Tables\Actions\DeleteAction::make()->label('Borrar')
                    ->after(function (GuiaFoto $record) {
                        try { Storage::disk('public')->delete($record->ruta); } catch (\Throwable $e) {}
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Aún no hay fotos de paquetes')
            ->emptyStateDescription('Subí las etiquetas desde "Fotos de paquetes" y aparecerán aquí.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListGuiaFotos::route('/')];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
