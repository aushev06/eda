<?php

namespace App\Models;

use App\Enums\PrinterConnectionType;
use App\Enums\PrinterType;
use Database\Factories\PrinterFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Printer extends Model
{
    /** @use HasFactory<PrinterFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'connection_type',
        'address',
        'port',
        'baud_rate',
        'tape_width',
        'chunk_size',
        'chunks_delay_ms',
        'tasks_delay_ms',
        'persist_connection',
        'print_images',
        'send_beep',
        'is_active',
        'comments',
    ];

    protected function casts(): array
    {
        return [
            'type' => PrinterType::class,
            'connection_type' => PrinterConnectionType::class,
            'port' => 'integer',
            'baud_rate' => 'integer',
            'tape_width' => 'integer',
            'chunk_size' => 'integer',
            'chunks_delay_ms' => 'integer',
            'tasks_delay_ms' => 'integer',
            'persist_connection' => 'boolean',
            'print_images' => 'boolean',
            'send_beep' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Drop settings that make no sense for the chosen connection, so a printer
     * moved from TCP to Bluetooth doesn't keep a stale port.
     */
    protected static function booted(): void
    {
        static::saving(function (self $printer): void {
            if ($printer->connection_type !== PrinterConnectionType::Network) {
                $printer->port = null;
            }

            if ($printer->connection_type !== PrinterConnectionType::Com) {
                $printer->baud_rate = null;
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, PrinterType $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Payload the POS app needs to open a connection and print.
     *
     * @return array<string, mixed>
     */
    public function toPosPayload(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'name' => $this->name,
            'connection_type' => $this->connection_type->value,
            'address' => $this->address,
            'port' => $this->port,
            'baud_rate' => $this->baud_rate,
            'tape_width' => $this->tape_width,
            'chunk_size' => $this->chunk_size,
            'chunks_delay_ms' => $this->chunks_delay_ms,
            'tasks_delay_ms' => $this->tasks_delay_ms,
            'persist_connection' => $this->persist_connection,
            'print_images' => $this->print_images,
            'send_beep' => $this->send_beep,
        ];
    }
}
