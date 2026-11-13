<?php

namespace Webkul\Churn\Services;

class RetentionAdvice
{
    /** Deterministic review guidance, never an inferred treatment or an automatic action. */
    public function recommend(?object $prediction, array $statistics, ?bool $inputMatches): string
    {
        if ($statistics['features'] === null) {
            return 'no_history';
        }
        if (! $statistics['eligible']) {
            return 'ineligible';
        }
        if ($prediction === null) {
            return 'missing';
        }
        if ((bool) $prediction->automatic_action_allowed || $prediction->model_status !== 'experimental'
            || ! in_array($prediction->risk, ['Alto', 'Medio', 'Bajo'], true)) {
            return 'invalid';
        }
        if ($inputMatches !== true) {
            return 'unverified';
        }
        if ((bool) $prediction->limited_history) {
            return 'limited';
        }

        return match ($prediction->risk) {
            'Alto' => 'high', 'Medio' => 'medium', 'Bajo' => 'low'
        };
    }
}
