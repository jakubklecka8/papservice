<?php

namespace App\Services;

use App\Models\Tariff;
use App\Models\Ticket;

/**
 * Cena zakázky = základ + km × sazba + příplatky, nebo ručně zadaná celková částka.
 * Co není u zakázky vyplněné, bere se z ceníku projektu. Při uzavření se ceník
 * „zamrazí“ do zakázky, takže pozdější změna ceníku staré zakázky nezmění.
 */
class PricingService
{
    /** @return array<string, mixed> */
    public function quote(Ticket $ticket): array
    {
        $tariff = Tariff::for($ticket->project_id, $ticket->service_type);
        $km = (float) ($ticket->relationLoaded('visits')
            ? $ticket->visits->sum('km')
            : $ticket->visits()->sum('km'));

        $currency = $ticket->price_currency ?? $tariff?->currency ?? 'CZK';
        $base = $ticket->price_base !== null ? (float) $ticket->price_base : ($tariff?->base_price ?? 0.0);
        $kmRate = $ticket->price_km_rate !== null ? (float) $ticket->price_km_rate : ($tariff?->km_rate ?? 0.0);
        $extra = (float) ($ticket->price_extra ?? 0);
        $kmAmount = round($km * $kmRate, 2);
        $computed = round($base + $kmAmount + $extra, 2);
        $manualTotal = $ticket->price_total !== null;

        return [
            'currency' => $currency,
            'base' => $base,
            'km' => $km,
            'km_rate' => $kmRate,
            'km_amount' => $kmAmount,
            'extra' => $extra,
            'computed' => $computed,
            'total' => $manualTotal ? (float) $ticket->price_total : $computed,
            'manual_total' => $manualTotal,
            'overrides' => [
                'currency' => $ticket->price_currency,
                'base' => $ticket->price_base !== null ? (float) $ticket->price_base : null,
                'km_rate' => $ticket->price_km_rate !== null ? (float) $ticket->price_km_rate : null,
                'extra' => $ticket->price_extra !== null ? (float) $ticket->price_extra : null,
                'total' => $manualTotal ? (float) $ticket->price_total : null,
            ],
            'note' => $ticket->price_note,
            'locked_at' => $ticket->price_locked_at?->toIso8601String(),
            'tariff' => $tariff ? [
                'id' => $tariff->id,
                'currency' => $tariff->currency,
                'base_price' => $tariff->base_price,
                'km_rate' => $tariff->km_rate,
                'note' => $tariff->note,
            ] : null,
        ];
    }

    /** Při uzavření zakázky zapíše ceník do zakázky (jen to, co dispečer nezměnil ručně) */
    public function lock(Ticket $ticket): void
    {
        if ($ticket->price_locked_at) {
            return;
        }
        $tariff = Tariff::for($ticket->project_id, $ticket->service_type);
        $ticket->forceFill([
            'price_currency' => $ticket->price_currency ?? $tariff?->currency,
            'price_base' => $ticket->price_base ?? $tariff?->base_price,
            'price_km_rate' => $ticket->price_km_rate ?? $tariff?->km_rate,
            'price_locked_at' => now(),
        ])->saveQuietly();
    }
}
