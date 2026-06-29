@php
    /**
     * Partial: FAQ JSON-LD schema (AEO / FAQPage)
     * Include on pages that should surface FAQs in search results.
     * Reads the `faqs` setting (JSON array) and outputs a FAQPage schema
     * using the current locale's question/answer fields.
     *
     * Usage: @include('partials.faq-schema')
     */
    $locale  = app()->getLocale();
    $rawFaqs = \App\Models\Setting::get('faqs');

    // The setting is stored as a JSON string; castValue handles 'string' type,
    // so we may receive a string or an already-decoded array.
    if (is_string($rawFaqs)) {
        $faqList = json_decode($rawFaqs, true) ?? [];
    } else {
        $faqList = is_array($rawFaqs) ? $rawFaqs : [];
    }

    $mainEntities = collect($faqList)
        ->filter(fn ($faq) => ! empty($faq['question_' . $locale] ?? ($faq['question_es'] ?? null)))
        ->map(fn ($faq) => [
            '@type'          => 'Question',
            'name'           => $faq['question_' . $locale] ?? $faq['question_es'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => $faq['answer_' . $locale] ?? ($faq['answer_es'] ?? ''),
            ],
        ])
        ->values()
        ->all();
@endphp

@if (! empty($mainEntities))
<script type="application/ld+json">{!! json_encode([
    '@context'   => 'https://schema.org',
    '@type'      => 'FAQPage',
    'mainEntity' => $mainEntities,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
