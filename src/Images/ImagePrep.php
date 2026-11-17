<?php

/**
 * Pollen: preparing an image's copy for a network.
 *
 * @package KraftySprouts\Pollen
 * @since   1.0.0
 */

declare(strict_types=1);

namespace KraftySprouts\Pollen\Images;

use GdImage;
use KraftySprouts\Pollen\Doors;
use KraftySprouts\Pollen\Networks\Network;
use KraftySprouts\Pollen\Networks\ShareFailed;
use Throwable;

/**
 * Makes a copy of a share's image cut to the network's best size, with the
 * site's logo in a corner when asked, and serves it from Pollen's own
 * address. Only images from the host named in Settings › Pollen are fetched;
 * the original is never touched, and anything that goes wrong sends the
 * original's address instead.
 *
 * @since 1.0.0
 */
final readonly class ImagePrep
{
    /**
     * @since 1.0.0
     */
    public function __construct(private Doors $doors) {}

    /**
     * Returns the address to send the network: a prepared copy's, or the original's.
     *
     * @since 1.0.0
     */
    public function prepare(string $source, Network $network): string
    {
        $settings = $this->doors->settings;
        $resize = $settings->bool('resize');
        $watermark = $settings->bool('watermark') && $settings->string('watermark_image') !== '';
        $host = strtolower((string) parse_url($source, PHP_URL_HOST));

        if ((! $resize && ! $watermark) || $host === '' || ! in_array($host, [strtolower((string) parse_url($settings->string('image_host'), PHP_URL_HOST))], true)) {
            return $source;
        }

        $existing = $this->doors->images->existing($source, $network->key());

        if ($existing !== null) {
            return $this->address($existing);
        }

        try {
            $made = self::make(
                $this->doors->http->bytes($source),
                $resize ? $network->limits()->imageSize : null,
                $watermark ? $this->doors->http->bytes($settings->string('watermark_image')) : null,
                $settings->string('watermark_corner'),
                $settings->int('watermark_size'),
            );
        } catch (ShareFailed|Throwable) {
            return $source;
        }

        if ($made === null) {
            return $source;
        }

        return $this->address($this->doors->images->store($source, $network->key(), 'image/jpeg', $made['bytes'], $made['width'], $made['height']));
    }

    /**
     * Returns a JPEG of the image cut to $size, covering it from the centre, with the logo in a corner; null when the bytes aren't an image.
     *
     * @param  array{0: int, 1: int}|null  $size
     * @return array{bytes: string, width: int, height: int}|null
     *
     * @since 1.0.0
     */
    public static function make(string $bytes, ?array $size, ?string $logo = null, string $corner = 'bottom-right', int $logoPercent = 18): ?array
    {
        $image = @imagecreatefromstring($bytes);

        if (! $image instanceof GdImage) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        [$targetW, $targetH] = $size ?? [$width, $height];

        // Cover the target box: scale until both sides fill it, then cut the middle.
        $scale = max($targetW / $width, $targetH / $height);
        $cropW = (int) round($targetW / $scale);
        $cropH = (int) round($targetH / $scale);
        $canvas = imagecreatetruecolor($targetW, $targetH);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, (int) (($width - $cropW) / 2), (int) (($height - $cropH) / 2), $targetW, $targetH, $cropW, $cropH);

        if ($logo !== null && ($mark = @imagecreatefromstring($logo)) instanceof GdImage) {
            self::watermark($canvas, $mark, $corner, $logoPercent);
        }

        ob_start();
        imagejpeg($canvas, null, 85);

        return ['bytes' => (string) ob_get_clean(), 'width' => $targetW, 'height' => $targetH];
    }

    /**
     * Places the logo in a corner, scaled to a share of the image's width, with a margin.
     */
    private static function watermark(GdImage $canvas, GdImage $mark, string $corner, int $percent): void
    {
        $canvasW = imagesx($canvas);
        $canvasH = imagesy($canvas);
        $markW = max(1, (int) round($canvasW * min(40, max(5, $percent)) / 100));
        $markH = max(1, (int) round(imagesy($mark) * $markW / imagesx($mark)));
        $margin = (int) round($canvasW * 0.03);
        $x = str_ends_with($corner, 'left') ? $margin : $canvasW - $markW - $margin;
        $y = str_starts_with($corner, 'top') ? $margin : $canvasH - $markH - $margin;

        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $mark, $x, $y, 0, 0, $markW, $markH, imagesx($mark), imagesy($mark));
    }

    /**
     * Returns the public address of a stored copy.
     */
    private function address(string $key): string
    {
        return $this->doors->site->address().'/pollen/image/'.$key.'.jpg';
    }
}
