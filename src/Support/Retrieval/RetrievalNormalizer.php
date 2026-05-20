<?php

namespace Mbs\ModelMind\Support\Retrieval;

class RetrievalNormalizer
{
    public function normalize(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $value = $this->normalizeArabic($value);

        if ((bool) config('model-mind.retrieval.normalization.strip_diacritics', true)) {
            $value = $this->stripDiacritics($value);
        }

        if ((bool) config('model-mind.retrieval.normalization.transliterate', true)) {
            $value = $this->transliterate($value);
        }

        return str($value)
            ->lower()
            ->replaceMatches('/[^\pL\pN]+/u', ' ')
            ->squish()
            ->toString();
    }

    /**
     * @return array<int, string>
     */
    public function terms(string $question): array
    {
        $stopWords = collect((array) config('model-mind.retrieval.stop_words', []))
            ->filter(fn (mixed $word): bool => is_string($word))
            ->map(fn (string $word): string => $this->normalize($word))
            ->filter()
            ->values()
            ->all();
        $minLength = max(1, (int) config('model-mind.retrieval.min_term_length', 2));
        $maxTerms = max(1, (int) config('model-mind.retrieval.max_terms', 8));

        return str($this->normalize($question))
            ->explode(' ')
            ->map(fn (string $term): string => trim($term))
            ->filter(fn (string $term): bool => $term !== ''
                && mb_strlen($term) >= $minLength
                && ! in_array($term, $stopWords, true))
            ->unique()
            ->take($maxTerms)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function tokens(string $value): array
    {
        return str($this->normalize($value))
            ->explode(' ')
            ->map(fn (string $token): string => trim($token))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeArabic(string $value): string
    {
        if (! (bool) config('model-mind.retrieval.normalization.multilingual', true)) {
            return $value;
        }

        $value = strtr($value, [
            'أ' => 'ا',
            'إ' => 'ا',
            'آ' => 'ا',
            'ٱ' => 'ا',
            'ى' => 'ي',
            'ئ' => 'ي',
            'ؤ' => 'و',
            'ة' => 'ه',
        ]);

        return preg_replace('/[\x{0640}\x{064B}-\x{065F}\x{0670}]/u', '', $value) ?? $value;
    }

    private function stripDiacritics(string $value): string
    {
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_D);

            if (is_string($normalized)) {
                $value = preg_replace('/\pM+/u', '', $normalized) ?? $value;
            }
        }

        return $value;
    }

    private function transliterate(string $value): string
    {
        if (function_exists('transliterator_transliterate')) {
            $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII', $value);

            if (is_string($transliterated)) {
                return $transliterated;
            }
        }

        return $value;
    }
}
