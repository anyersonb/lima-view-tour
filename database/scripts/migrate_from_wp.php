<?php

/**
 * One-shot migration script: WooCommerce (WP) → Lima View Tours (Laravel).
 *
 * Reads the `limaview_newlima` database (a snapshot of the production
 * WordPress site) and inserts the equivalent rows into the Laravel
 * `lima_tours` schema. Idempotent: it WIPES the target tables first
 * (tours, bookings, regions/categories untouched).
 *
 * Run locally with: php database/scripts/migrate_from_wp.php
 *
 * Env vars (optional overrides):
 *   WP_DB     default "limaview_newlima"
 *   LARAVEL_DB default "lima_tours"
 *   DB_HOST   default "127.0.0.1"
 *   DB_USER   default "root"
 *   DB_PASS   default ""
 *
 * The script also produces:
 *   storage/app/wp_migration/image_manifest.txt — list of wp-content/uploads
 *   paths whose images must be copied to public/uploads/tours/ on prod.
 */

declare(strict_types=1);

$wpDb     = getenv('WP_DB')      ?: 'limaview_newlima';
$lvDb     = getenv('LARAVEL_DB') ?: 'lima_tours';
$dbHost   = getenv('DB_HOST')    ?: '127.0.0.1';
$dbUser   = getenv('DB_USER')    ?: 'root';
$dbPass   = getenv('DB_PASS')    ?: '';

$wp = new PDO("mysql:host={$dbHost};dbname={$wpDb};charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$lv = new PDO("mysql:host={$dbHost};dbname={$lvDb};charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

echo "→ Reading from {$wpDb}, writing to {$lvDb}\n\n";

// ─── Region & category lookups (Laravel side) ───────────────────────────────
$regionByName = [];
foreach ($lv->query("SELECT id, name_es, slug FROM regions") as $r) {
    $regionByName[mb_strtolower($r['name_es'])] = (int) $r['id'];
    $regionByName[mb_strtolower($r['slug'])]    = (int) $r['id'];
}

$categoryBySlug = [];
foreach ($lv->query("SELECT id, slug, name_es FROM categories") as $c) {
    $categoryBySlug[$c['slug']]                  = (int) $c['id'];
    $categoryBySlug[mb_strtolower($c['name_es'])] = (int) $c['id'];
}

// Map WP product_cat → Laravel region by name. The WP categories with names
// matching a Laravel region win; everything else falls back to NULL.
$wpRegionByPostId = [];
$stmt = $wp->query("
    SELECT tr.object_id AS post_id, t.name AS term_name
    FROM wp_term_relationships tr
    JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
    JOIN wp_terms t ON t.term_id = tt.term_id
    WHERE tt.taxonomy = 'product_cat'
");
foreach ($stmt as $row) {
    $key = mb_strtolower($row['term_name']);
    if (isset($regionByName[$key]) && empty($wpRegionByPostId[$row['post_id']])) {
        $wpRegionByPostId[$row['post_id']] = $regionByName[$key];
    }
}

// ─── Attachment lookup (post_id → filename in wp-content/uploads) ──────────
$attachments = [];
$stmt = $wp->query("
    SELECT p.ID, pm.meta_value AS file
    FROM wp_posts p
    JOIN wp_postmeta pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attached_file'
    WHERE p.post_type = 'attachment'
");
foreach ($stmt as $row) {
    $attachments[(int) $row['ID']] = $row['file']; // e.g. "2025/06/foo.jpg"
}
echo "  • Indexed " . count($attachments) . " attachments\n";

// Collect a unique list of source paths to ship to production later.
$shippedImages = [];
$shipImage = function (?int $attachmentId) use ($attachments, &$shippedImages): ?string {
    if (! $attachmentId || ! isset($attachments[$attachmentId])) {
        return null;
    }
    $rel = $attachments[$attachmentId]; // "YYYY/MM/file.jpg"
    $shippedImages[$rel] = true;
    return 'tours/' . basename($rel);
};

// ─── Load product meta into a single in-memory map ─────────────────────────
$productIds = [];
foreach ($wp->query("SELECT ID FROM wp_posts WHERE post_type='product' AND post_status='publish'") as $row) {
    $productIds[] = (int) $row['ID'];
}
echo "  • " . count($productIds) . " published products\n";

$metaByPost = [];
$inIds = implode(',', array_map('intval', $productIds));
$stmt = $wp->query("SELECT post_id, meta_key, meta_value FROM wp_postmeta WHERE post_id IN ({$inIds})");
foreach ($stmt as $row) {
    $metaByPost[(int) $row['post_id']][$row['meta_key']] = $row['meta_value'];
}

// ─── Helpers ────────────────────────────────────────────────────────────────
$stripHtml = function (?string $html): ?string {
    if ($html === null || $html === '') return null;
    // Strip Elementor wrappers and HTML, keep paragraphs as plain lines.
    $text = preg_replace('/<\/?(div|span|a)[^>]*>/i', '', $html);
    $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
    $text = preg_replace('/<\/p>/i', "\n\n", $text);
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/[ \t]+/", ' ', $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return trim($text) ?: null;
};

$extractBullets = function (?string $html) use ($stripHtml): array {
    if ($html === null) return [];
    if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $html, $m) && ! empty($m[1])) {
        return array_values(array_filter(array_map(fn($s) => trim($stripHtml($s)), $m[1])));
    }
    $plain = $stripHtml($html);
    if (! $plain) return [];
    $lines = preg_split("/\r?\n+/", $plain);
    return array_values(array_filter(array_map('trim', $lines)));
};

$badgeTypeFromColor = function (?string $hex): ?string {
    if (! $hex) return null;
    $hex = strtolower(ltrim($hex, '#'));
    $rgb = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    [$r, $g, $b] = $rgb;
    if ($r > 200 && $g < 100 && $b < 100) return 'error';   // red
    if ($r > 200 && $g > 130 && $b < 100) return 'warn';    // orange
    if ($g > 150 && $r < 130) return 'success';             // green
    return 'success';
};

// ─── Wipe target tables ─────────────────────────────────────────────────────
$lv->exec('SET FOREIGN_KEY_CHECKS=0');
$lv->exec('TRUNCATE TABLE bookings');
$lv->exec('TRUNCATE TABLE tours');
$lv->exec('SET FOREIGN_KEY_CHECKS=1');

// ─── Insert tours ──────────────────────────────────────────────────────────
$insertTour = $lv->prepare("
    INSERT INTO tours
      (slug, region_id, title_es, title_en, subtitle_es, description_es,
       itinerary_es, includes_es, recommendations_es, notes_es,
       price, price_before, currency, duration, language, group_type,
       departure_time, cover_image, gallery, badge_text, badge_type,
       rating, reviews_count, is_featured, is_published, `order`,
       created_at, updated_at)
    VALUES
      (:slug, :region_id, :title_es, :title_en, :subtitle_es, :description_es,
       :itinerary_es, :includes_es, :recommendations_es, :notes_es,
       :price, :price_before, 'USD', :duration, :language, :group_type,
       :departure_time, :cover_image, :gallery, :badge_text, :badge_type,
       :rating, :reviews_count, :is_featured, 1, :order,
       :created_at, :updated_at)
");

$tourCount = 0;
foreach ($productIds as $idx => $pid) {
    $post = $wp->prepare("SELECT post_title, post_name, post_content, post_excerpt, post_date FROM wp_posts WHERE ID = ?");
    $post->execute([$pid]);
    $post = $post->fetch();
    if (! $post) continue;

    $meta = $metaByPost[$pid] ?? [];

    // Build itinerary JSON from _itinerario_N_(descripcion|hora|imagen)
    $itinerary = [];
    for ($i = 0; $i < 12; $i++) {
        $desc = $meta["itinerario_{$i}_descripcion"] ?? null;
        $hora = $meta["itinerario_{$i}_hora"]        ?? null;
        $img  = $meta["itinerario_{$i}_imagen"]      ?? null;
        if (! $desc && ! $hora) continue;
        $imgPath = $img ? $shipImage((int) $img) : null;
        $itinerary[] = [
            'time'        => $hora ? trim($hora) : null,
            'description' => $desc ? trim($stripHtml($desc)) : null,
            'image'       => $imgPath,
        ];
    }

    $coverPath  = $shipImage(isset($meta['_thumbnail_id']) ? (int) $meta['_thumbnail_id'] : null);
    $galleryIds = $meta['_product_image_gallery'] ?? '';
    $galleryPaths = [];
    foreach (array_filter(array_map('intval', explode(',', $galleryIds))) as $gid) {
        if ($p = $shipImage($gid)) $galleryPaths[] = $p;
    }

    $badgeText = trim((string) ($meta['estado_del_tour'] ?? $meta['cupos'] ?? ''));
    $badgeType = $badgeTypeFromColor($meta['color'] ?? null);

    $title = $post['post_title'];

    // post_excerpt sometimes contains a <ul>/<li> block instead of a real subtitle;
    // demote it to the description body and keep subtitle blank in that case.
    $rawExcerpt   = trim((string) ($post['post_excerpt'] ?? ''));
    $hasHtmlList  = (bool) preg_match('/<(ul|ol|li|p|div|b|strong|h\d)\b/i', $rawExcerpt);
    $plainExcerpt = $stripHtml($rawExcerpt);
    $subtitle     = (! $hasHtmlList && $plainExcerpt && mb_strlen($plainExcerpt) <= 240)
        ? $plainExcerpt
        : null;

    $descParts = array_filter([
        $plainExcerpt,
        $stripHtml($post['post_content']),
    ]);
    $description = $descParts ? implode("\n\n", $descParts) : null;

    $insertTour->execute([
        'slug'              => $post['post_name'] ?: 'tour-' . $pid,
        'region_id'         => $wpRegionByPostId[$pid] ?? null,
        'title_es'          => $title,
        'title_en'          => $title, // por ahora copia, se traduce manualmente desde Filament
        'subtitle_es'       => $subtitle,
        'description_es'    => $description,
        'itinerary_es'      => $itinerary ? json_encode($itinerary, JSON_UNESCAPED_UNICODE) : null,
        'includes_es'       => ($inc = $extractBullets($meta['incluye'] ?? null)) ? json_encode($inc, JSON_UNESCAPED_UNICODE) : null,
        'recommendations_es'=> $stripHtml($meta['recomendaciones'] ?? null),
        'notes_es'          => $stripHtml($meta['recojo_y_retorno_al_hotel'] ?? null),
        'price'             => (float) ($meta['_price'] ?? 0),
        'price_before'      => ! empty($meta['precio_antes']) ? (float) $meta['precio_antes'] : null,
        'duration'          => trim((string) ($meta['horas'] ?? $meta['tiempo'] ?? '')) ?: null,
        'language'          => trim((string) ($meta['idioma'] ?? 'Español / Inglés')),
        'group_type'        => trim((string) ($meta['personas'] ?? 'Tour Grupal')),
        'departure_time'    => trim((string) ($meta['hora_de_salida'] ?? '')) ?: null,
        'cover_image'       => $coverPath,
        'gallery'           => $galleryPaths ? json_encode($galleryPaths, JSON_UNESCAPED_UNICODE) : null,
        'badge_text'        => $badgeText ?: null,
        'badge_type'        => $badgeType,
        'rating'            => (float) ($meta['_wc_average_rating'] ?? 4.8) ?: 4.8,
        'reviews_count'     => (int) ($meta['_wc_review_count'] ?? 0),
        'is_featured'       => stripos($badgeText, 'vendido') !== false ? 1 : 0,
        'order'             => $idx,
        'created_at'        => $post['post_date'],
        'updated_at'        => $post['post_date'],
    ]);
    $tourCount++;
    echo "    ✓ Tour #{$pid}: {$title}\n";
}

echo "\n  • Inserted {$tourCount} tours\n\n";

// ─── Insert bookings ───────────────────────────────────────────────────────
$bookingCount = 0;
$insertBooking = $lv->prepare("
    INSERT INTO bookings
      (reference, tour_id, tour_title_snapshot,
       customer_name, customer_email, customer_phone,
       travel_date, adults, children,
       unit_price, total_price, currency,
       status, payment_status, payment_method, payment_reference,
       notes, locale, created_at, updated_at)
    VALUES
      (:reference, :tour_id, :tour_title_snapshot,
       :customer_name, :customer_email, :customer_phone,
       :travel_date, :adults, :children,
       :unit_price, :total_price, :currency,
       :status, :payment_status, :payment_method, :payment_reference,
       :notes, 'es', :created_at, :updated_at)
");

// Slug → tour_id en Laravel (after insert)
$tourBySlug = [];
foreach ($lv->query("SELECT id, slug, title_es FROM tours") as $t) {
    $tourBySlug[$t['slug']] = ['id' => (int) $t['id'], 'title' => $t['title_es']];
}

$wpSlugByPostId = [];
foreach ($wp->query("SELECT ID, post_name FROM wp_posts WHERE post_type='product'") as $p) {
    $wpSlugByPostId[(int) $p['ID']] = $p['post_name'];
}

// Each order may have multiple line items, we collapse them: 1 booking per order
$statusMap = [
    'wc-completed'  => ['status' => 'confirmed', 'payment_status' => 'paid'],
    'wc-processing' => ['status' => 'pending',   'payment_status' => 'pending'],
    'wc-pending'    => ['status' => 'pending',   'payment_status' => 'pending'],
    'wc-cancelled'  => ['status' => 'cancelled', 'payment_status' => 'refunded'],
    'wc-refunded'   => ['status' => 'cancelled', 'payment_status' => 'refunded'],
    'wc-on-hold'    => ['status' => 'pending',   'payment_status' => 'pending'],
];

$orders = $wp->query("
    SELECT o.id, o.status, o.currency, o.total_amount, o.billing_email,
           o.date_created_gmt, o.payment_method_title, o.customer_note,
           a.first_name, a.last_name, a.phone
    FROM wp_wc_orders o
    LEFT JOIN wp_wc_order_addresses a ON a.order_id = o.id AND a.address_type = 'billing'
    WHERE o.type = 'shop_order'
    ORDER BY o.id
");

foreach ($orders as $o) {
    // Get the first line item (tour reserved)
    $itemStmt = $wp->prepare("
        SELECT oi.order_item_name,
               MAX(CASE WHEN oim.meta_key = '_product_id' THEN oim.meta_value END) AS product_id,
               MAX(CASE WHEN oim.meta_key = '_qty'        THEN oim.meta_value END) AS qty,
               MAX(CASE WHEN oim.meta_key = '_line_total' THEN oim.meta_value END) AS line_total,
               MAX(CASE WHEN oim.meta_key = 'Booking Date' OR oim.meta_key = '_booking_date' THEN oim.meta_value END) AS booking_date
        FROM wp_woocommerce_order_items oi
        LEFT JOIN wp_woocommerce_order_itemmeta oim ON oim.order_item_id = oi.order_item_id
        WHERE oi.order_id = ? AND oi.order_item_type = 'line_item'
        GROUP BY oi.order_item_id
        LIMIT 1
    ");
    $itemStmt->execute([$o['id']]);
    $item = $itemStmt->fetch() ?: [];

    $productId   = (int) ($item['product_id'] ?? 0);
    $slug        = $wpSlugByPostId[$productId] ?? null;
    $tourEntry   = $slug ? ($tourBySlug[$slug] ?? null) : null;
    $tourId      = $tourEntry['id'] ?? null;
    $tourTitle   = $item['order_item_name'] ?? ($tourEntry['title'] ?? '—');

    $qty         = max(1, (int) ($item['qty'] ?? 1));
    $unitPrice   = $qty ? round(((float) ($item['line_total'] ?? $o['total_amount'])) / $qty, 2) : (float) $o['total_amount'];

    $statusInfo  = $statusMap[$o['status']] ?? ['status' => 'pending', 'payment_status' => 'pending'];

    $name = trim(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? ''));
    if ($name === '') {
        $name = $o['billing_email'] ?: ('Cliente #' . $o['id']);
    }

    $travelDate = $item['booking_date'] ?? $o['date_created_gmt'];

    $insertBooking->execute([
        'reference'           => 'WC-' . $o['id'],
        'tour_id'             => $tourId,
        'tour_title_snapshot' => $tourTitle,
        'customer_name'       => $name,
        'customer_email'      => $o['billing_email'] ?: ('order-' . $o['id'] . '@unknown.local'),
        'customer_phone'      => $o['phone'] ?: null,
        'travel_date'         => substr((string) $travelDate, 0, 10),
        'adults'              => $qty,
        'children'            => 0,
        'unit_price'          => $unitPrice,
        'total_price'         => (float) $o['total_amount'],
        'currency'            => $o['currency'] ?: 'USD',
        'status'              => $statusInfo['status'],
        'payment_status'      => $statusInfo['payment_status'],
        'payment_method'      => $o['payment_method_title'] ?: null,
        'payment_reference'   => null,
        'notes'               => $o['customer_note'] ?: null,
        'created_at'          => $o['date_created_gmt'],
        'updated_at'          => $o['date_created_gmt'],
    ]);
    $bookingCount++;
}

echo "  • Inserted {$bookingCount} bookings\n\n";

// ─── Dump image manifest for the FTP copy step ─────────────────────────────
$manifestDir = __DIR__ . '/../../storage/app/wp_migration';
if (! is_dir($manifestDir)) mkdir($manifestDir, 0755, true);
$manifestPath = $manifestDir . '/image_manifest.txt';
file_put_contents($manifestPath, implode("\n", array_keys($shippedImages)));
echo "  • Wrote " . count($shippedImages) . " image paths to " . realpath($manifestPath) . "\n";

echo "\n✓ Migration complete.\n";
