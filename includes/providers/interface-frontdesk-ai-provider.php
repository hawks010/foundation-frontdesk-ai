<?php
if (!defined('ABSPATH')) { exit; }

interface Frontdesk_AI_Provider {
    public function answer(array $request, array $context, array $config): array;
    public function validate(array $config): array;
}
