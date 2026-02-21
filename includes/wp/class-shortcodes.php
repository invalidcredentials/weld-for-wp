<?php
/**
 * Shortcode handlers.
 */

defined( 'ABSPATH' ) || exit;

class WeldPress_Shortcodes {

    public static function init() {
        add_shortcode( 'weldpress_connect', array( __CLASS__, 'connect_button' ) );
        add_shortcode( 'weldpress_wallet_badge', array( __CLASS__, 'wallet_badge' ) );
        add_shortcode( 'weldpress_send', array( __CLASS__, 'send_form' ) );
    }

    /**
     * [weldpress_connect] — renders a connect wallet button + modal.
     *
     * Attributes:
     *   label  — button text (default: "Connect Wallet")
     *   theme  — "light" or "dark" (default: "light")
     */
    public static function connect_button( $atts ) {
        $atts = shortcode_atts( array(
            'label' => 'Connect Wallet',
            'theme' => 'light',
        ), $atts, 'weldpress_connect' );

        self::enqueue();

        $theme = in_array( $atts['theme'], array( 'light', 'dark' ), true ) ? $atts['theme'] : 'light';

        return sprintf(
            '<div class="weldpress-connect" data-weldpress="connect" data-theme="%s" data-label="%s"></div>',
            esc_attr( $theme ),
            esc_attr( $atts['label'] )
        );
    }

    /**
     * [weldpress_wallet_badge] — shows connected wallet address + balance.
     */
    public static function wallet_badge( $atts ) {
        self::enqueue();

        return '<div class="weldpress-badge" data-weldpress="badge"></div>';
    }

    /**
     * [weldpress_send] — renders a send-ADA form.
     *
     * Attributes:
     *   to       — pre-filled recipient address (optional)
     *   amount   — pre-filled ADA amount (optional)
     */
    public static function send_form( $atts ) {
        $atts = shortcode_atts( array(
            'to'     => '',
            'amount' => '',
        ), $atts, 'weldpress_send' );

        self::enqueue();

        return sprintf(
            '<div class="weldpress-send" data-weldpress="send" data-to="%s" data-amount="%s"></div>',
            esc_attr( $atts['to'] ),
            esc_attr( $atts['amount'] )
        );
    }

    /**
     * Enqueue frontend assets when a shortcode is used.
     */
    private static function enqueue() {
        wp_enqueue_script( 'weldpress' );
        wp_enqueue_style( 'weldpress' );
    }
}
