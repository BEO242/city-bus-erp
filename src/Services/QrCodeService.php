<?php

declare(strict_types=1);

namespace CityBus\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Génère des QR codes en base64 PNG ou en PNG binaire.
 */
final class QrCodeService
{
    public function generateBase64(string $data, int $size = 250, int $margin = 8): string
    {
        return base64_encode($this->generatePng($data, $size, $margin));
    }

    public function generatePng(string $data, int $size = 250, int $margin = 8): string
    {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($data)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::High)
            ->size($size)
            ->margin($margin)
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->build();

        return $result->getString();
    }

    public function hash(string $qrCode): string
    {
        return hash('sha256', $qrCode);
    }

    /** Génère un UUID v4 utilisé comme valeur du QR code. */
    public function generateUuid(): string
    {
        return \Ramsey\Uuid\Uuid::uuid4()->toString();
    }

    /**
     * Génère un code alphanumérique court (6 chars) unique dans une table donnée.
     * Alphabet : ABCDEFGHJKLMNPQRSTUVWXYZ23456789 (pas de 0/O/1/I pour lisibilité).
     */
    public function generateShortCode(string $table = 'pre_printed_tickets', string $column = 'short_code'): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $len   = strlen($chars);
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $chars[random_int(0, $len - 1)];
            }
            $exists = \CityBus\Core\Database::selectOne(
                "SELECT id FROM {$table} WHERE {$column} = ? LIMIT 1",
                [$code]
            );
        } while ($exists);
        return $code;
    }
}
