<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
interface Orion_AI_Provider {
    public function chat( array $messages, array $tools = array() ): array;
}
