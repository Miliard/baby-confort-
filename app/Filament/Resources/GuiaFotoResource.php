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

                // UN SOLO CLIC: copia el mensaje y marca la fila como enviada.
                // Rojo = falta enviar · Verde = ya enviado.
                Tables\Columns\ViewColumn::make('mensaje')->label('Mensaje')
                    ->view('filament.tables.columns.copiar-enviar'),

                Tables\Columns\TextColumn::make('lote')->label('Lote')
                    ->formatStateUsing(fn (GuiaFoto $record) => $record->loteBonito())
                    ->badge()->color('gray')->sortable()
                    ->tooltip('Tanda en la que se subió esta foto'),

                Tables\Columns\TextColumn::make('created_at')->label('Fecha')
                    ->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),

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
                    // Sin esto, las guías sin foto muestran el ícono de imagen
                    // rota y parece un error. Ahora sale un recuadro neutro.
                    ->defaultImageUrl('data:image/svg+xml;utf8,' . rawurlencode(
                        '<svg xmlns="http://www.w3.org/2000/svg" width="42" height="42">'
                        . '<rect width="42" height="42" rx="6" fill="#e7ebf0"/>'
                        . '<path d="M13 26l5-6 4 4 3-3 4 5z" fill="#b9c3cf"/>'
                        . '<circle cx="16" cy="16" r="2.5" fill="#b9c3cf"/></svg>'
                    ))
                    ->action(
                        Tables\Actions\Action::make('abrir_foto')
                            ->modalHeading(fn (GuiaFoto $record) => 'Etiqueta de la guía ' . $record->guia)
                            ->modalContent(fn (GuiaFoto $record) => new \Illuminate\Support\HtmlString(
                                '<img src="' . e($record->url()) . '" alt="Etiqueta" style="width:100%;max-height:80vh;object-fit:contain;border-radius:10px;display:block">'
                            ))
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Cerrar')
                            ->modalWidth('7xl')
                    )
                    ->tooltip('Clic para ver la etiqueta en grande'),

                // Aviso de cuánto le queda a la imagen antes de borrarse del disco.
                // Los datos del pedido se quedan; solo se va la foto.
                Tables\Columns\TextColumn::make('vence')->label('Foto')
                    ->badge()
                    ->state(function (GuiaFoto $record) {
                        // Tres estados distintos, que antes se confundían en uno:
                        //  · nunca se subió foto (la guía entró por el PDF)
                        //  · se subió y ya se venció
                        //  · está y le quedan X días
                        if (! $record->ruta) {
                            return $record->foto_borrada_at ? 'Ya borrada' : 'Falta subirla';
                        }

                        $dias = \App\Http\Controllers\GuiaFotoController::diasDeFotos();
                        $faltan = (int) ceil(now()->diffInDays(
                            $record->created_at?->copy()->addDays($dias) ?? now(), false
                        ));

                        if ($faltan <= 0) return 'Se borra hoy';
                        return $faltan === 1 ? 'Queda 1 día' : "Quedan {$faltan} días";
                    })
                    ->color(function (GuiaFoto $record) {
                        if (! $record->ruta) {
                            return $record->foto_borrada_at ? 'gray' : 'warning';
                        }
                        $dias = \App\Http\Controllers\GuiaFotoController::diasDeFotos();
                        return now()->diffInDays($record->created_at ?? now()) >= $dias - 5
                            ? 'danger' : 'success';
                    })
                    ->tooltip(fn (GuiaFoto $record) => $record->ruta
                        ? 'Pasado este tiempo se borra solo la imagen. La guía, el cliente y qué llevaba se quedan guardados.'
                        : ($record->foto_borrada_at
                            ? 'La imagen se venció y se borró del disco. Los datos del pedido siguen guardados.'
                            : 'Esta guía todavía no tiene foto: subila desde Guías → Fotos.'))
                    ->toggleable(),
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

                // Cada tanda de fotos subidas junta es un lote (mañana / tarde, etc.)
                Tables\Filters\SelectFilter::make('lote')
                    ->label('Lote de subida')
                    ->options(function () {
                        try {
                            return GuiaFoto::query()
                                ->whereNotNull('lote')
                                ->select('lote')->distinct()
                                ->orderByDesc('lote')->limit(30)->pluck('lote')
                                ->mapWithKeys(function ($l) {
                                    try {
                                        $f = \Illuminate\Support\Carbon::parse($l);
                                        return [$l => $f->format('d/m') . ' · ' . $f->format('h:i A')];
                                    } catch (\Throwable $e) {
                                        return [$l => $l];
                                    }
                                })->all();
                        } catch (\Throwable $e) {
                            return [];
                        }
                    })
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null)
                        ? $query->where('lote', $data['value'])
                        : $query),

                Tables\Filters\SelectFilter::make('estado_envio')
                    ->label('Estado')
                    ->options(['pendiente' => 'Sin enviar', 'enviado' => 'Ya enviados'])
                    ->query(fn ($query, array $data) => match ($data['value'] ?? null) {
                        'pendiente' => $query->whereNull('enviado_at'),
                        'enviado'   => $query->whereNotNull('enviado_at'),
                        default     => $query,
                    }),

                Tables\Filters\Filter::make('sin_telefono')
                    ->label('Solo las que les falta el teléfono')
                    ->query(fn ($query) => $query->where(fn ($q) => $q->whereNull('telefono')->orWhere('telefono', ''))),
            ])
            // Las ya enviadas se ven atenuadas para distinguirlas de un vistazo.
            ->recordClasses(fn (GuiaFoto $record) => $record->enviado_at ? 'opacity-60' : null)
            ->actions([
                // Abre la etiqueta en grande para revisar que la guía y el teléfono estén bien.
                Tables\Actions\Action::make('ver_foto')
                    ->label('Ver foto')->icon('heroicon-o-photo')->color('gray')
                    ->modalHeading(fn (GuiaFoto $record) => 'Etiqueta de la guía ' . $record->guia)
                    ->modalContent(fn (GuiaFoto $record) => new \Illuminate\Support\HtmlString(
                        '<img src="' . e($record->url()) . '" alt="Etiqueta" '
                        . 'style="width:100%;max-height:80vh;object-fit:contain;border-radius:10px;display:block">'
                        . '<div style="margin-top:10px;font-size:13px;color:#6b7280;text-align:center">'
                        . 'Guía <b>' . e($record->guia) . '</b>'
                        . ($record->telefono ? ' · Tel <b>' . e($record->telefono) . '</b>' : '')
                        . ($record->nombre ? ' · ' . e($record->nombre) : '')
                        . '</div>'
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl'),

                Tables\Actions\DeleteAction::make()->label('Borrar')
                    ->after(function (GuiaFoto $record) {
                        try { Storage::disk('public')->delete($record->ruta); } catch (\Throwable $e) {}
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('marcar_enviados')
                        ->label('Marcar como enviados')->icon('heroicon-o-check')->color('success')
                        ->action(fn ($records) => $records->each->update(['enviado_at' => now()]))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('marcar_pendientes')
                        ->label('Marcar como pendientes')->icon('heroicon-o-arrow-uturn-left')->color('warning')
                        ->action(fn ($records) => $records->each->update(['enviado_at' => null]))
                        ->deselectRecordsAfterCompletion(),

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
