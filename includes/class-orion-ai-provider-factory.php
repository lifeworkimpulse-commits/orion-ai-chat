<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Orion_AI_Provider_Factory {
    public static function create( array $settings ): Orion_AI_Provider {
        $provider = Orion_AI_Settings::provider( $settings );
        if ( 'google' === $provider ) {
            return new Orion_Gemini_Client(
                Orion_AI_Settings::google_key( $settings ),
                (string) ( $settings['google_model'] ?? 'gemini-2.5-flash' )
            );
        }
        return new Orion_OpenRouter_Client(
            Orion_AI_Settings::openrouter_key( $settings ),
            (string) ( $settings['openrouter_model'] ?? 'openrouter/free' )
        );
    }
}
