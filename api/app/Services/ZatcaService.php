<?php

namespace App\Services;

use App\Models\Tenant;

/**
 * ZATCA (Saudi e-invoicing) — Phase 1 (Generation) support.
 *
 * Builds the Base64-encoded TLV payload for the invoice QR code: 5 tags —
 * seller name, VAT registration number, timestamp (ISO 8601), invoice total
 * (incl. VAT) and VAT amount. Each field is Tag(1 byte) + Length(1 byte, the
 * byte-length of the value) + Value(UTF-8), concatenated then Base64-encoded.
 *
 * Phase 2 (XML UBL 2.1, cryptographic stamp, clearance via the Fatoora platform)
 * needs ZATCA onboarding credentials and is out of scope for this generator.
 */
class ZatcaService
{
    public static function enabled(?Tenant $tenant = null): bool
    {
        $tenant ??= app()->bound('currentTenant') ? app('currentTenant') : null;

        return $tenant && (bool) (($tenant->settings ?? [])['einvoice_enabled'] ?? false);
    }

    public static function vatNumber(?Tenant $tenant = null): ?string
    {
        $tenant ??= app('currentTenant');

        return ($tenant->settings ?? [])['tax_number'] ?? null;
    }

    /** A valid KSA VAT registration number: 15 digits, starts and ends with 3. */
    public static function validVat(?string $vat): bool
    {
        return (bool) $vat && (bool) preg_match('/^3\d{13}3$/', $vat);
    }

    private static function tlv(int $tag, string $value): string
    {
        return chr($tag).chr(strlen($value)).$value;   // strlen = byte length
    }

    /**
     * The Base64 TLV payload for the QR, or null when e-invoicing is off / no VAT no.
     *
     * @param  float   $total     invoice total including VAT
     * @param  float   $vat       VAT amount
     * @param  string  $timestamp ISO 8601, e.g. 2026-10-26T15:30:00Z
     */
    public function qrPayload(Tenant $tenant, float $total, float $vat, string $timestamp): ?string
    {
        if (! self::enabled($tenant) || ! ($vatNo = self::vatNumber($tenant))) {
            return null;
        }
        $fmt = fn (float $n) => number_format($n, 2, '.', '');
        $payload = self::tlv(1, (string) $tenant->name)
            .self::tlv(2, $vatNo)
            .self::tlv(3, $timestamp)
            .self::tlv(4, $fmt($total))
            .self::tlv(5, $fmt($vat));

        return base64_encode($payload);
    }

    /** E-invoicing block for a tenant (for print headers / settings). */
    public function meta(Tenant $tenant): array
    {
        $vat = self::vatNumber($tenant);

        return ['enabled' => self::enabled($tenant), 'vat_number' => $vat, 'vat_valid' => self::validVat($vat)];
    }
}
