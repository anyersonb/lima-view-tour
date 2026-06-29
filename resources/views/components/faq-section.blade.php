@php
    /**
     * Component: FAQ accordion (AEO front-end section)
     * Renders FAQs from the `faqs` setting as an Alpine.js accordion.
     * If no FAQs are configured, the component renders nothing.
     */
    $locale  = app()->getLocale();
    $rawFaqs = \App\Models\Setting::get('faqs');

    if (is_string($rawFaqs)) {
        $faqList = json_decode($rawFaqs, true) ?? [];
    } else {
        $faqList = is_array($rawFaqs) ? $rawFaqs : [];
    }

    // Filter: keep only FAQs that have at least a question in the current locale or ES
    $faqs = collect($faqList)->filter(
        fn ($faq) => ! empty($faq['question_' . $locale] ?? ($faq['question_es'] ?? null))
    )->values()->all();
@endphp

@if (! empty($faqs))
<section class="bg-cream-100 px-5 py-10 lg:py-16" aria-labelledby="faq-title">
    <div class="container mx-auto max-w-3xl">

        <div class="text-center mb-8">
            <p class="text-[10px] uppercase tracking-[0.25em] text-orange-600 font-bold">
                {{ app()->getLocale() === 'en' ? 'FREQUENT QUESTIONS' : (app()->getLocale() === 'pt' ? 'PERGUNTAS FREQUENTES' : 'PREGUNTAS FRECUENTES') }}
            </p>
            <h2 id="faq-title" class="mt-2 font-display text-3xl lg:text-4xl text-teal-800">
                {{ app()->getLocale() === 'en' ? 'FAQ' : (app()->getLocale() === 'pt' ? 'Perguntas Frequentes' : 'Preguntas Frecuentes') }}
            </h2>
            <div class="flex items-center justify-center gap-2 mt-4 mb-0" aria-hidden="true">
                <span class="h-px w-8 bg-orange-500/50"></span>
                <svg class="w-3 h-3 text-orange-500" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l1.5 8.5L22 12l-8.5 1.5L12 22l-1.5-8.5L2 12l8.5-1.5L12 2z"/></svg>
                <span class="h-px w-8 bg-orange-500/50"></span>
            </div>
        </div>

        <div class="space-y-2" itemscope itemtype="https://schema.org/FAQPage">

            @foreach ($faqs as $i => $faq)
                @php
                    $question = $faq['question_' . $locale] ?? ($faq['question_es'] ?? '');
                    $answer   = $faq['answer_' . $locale]   ?? ($faq['answer_es']   ?? '');
                @endphp
                <div x-data="{ open: {{ $i === 0 ? 'true' : 'false' }} }"
                     class="bg-white rounded-2xl shadow-sm ring-1 ring-teal-800/5 overflow-hidden"
                     itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">

                    <button type="button"
                            @click="open = !open"
                            :aria-expanded="open"
                            class="w-full flex items-center justify-between gap-4 px-6 py-4 text-left font-semibold text-teal-800 text-sm lg:text-base hover:bg-cream-50 transition"
                            aria-controls="faq-answer-{{ $i }}">
                        <span itemprop="name">{{ $question }}</span>
                        <span class="shrink-0 w-6 h-6 rounded-full bg-orange-500/10 grid place-items-center text-orange-500 transition-transform duration-200"
                              :class="open ? 'rotate-45' : ''">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                        </span>
                    </button>

                    <div id="faq-answer-{{ $i }}"
                         x-show="open"
                         x-collapse
                         itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                        <div class="px-6 pb-5 pt-0 text-sm text-teal-800/75 leading-relaxed border-t border-cream-100"
                             itemprop="text">
                            {!! nl2br(e($answer)) !!}
                        </div>
                    </div>
                </div>
            @endforeach

        </div>
    </div>
</section>
@endif
