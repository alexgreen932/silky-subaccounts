<?php 

namespace App\Services\WooCommerce;

class SetLoyaltyLevel {

    public function __construct()
    {
        add_action('show_user_profile', [$this, 'add_loyalty_level_field'], 5);
        add_action('edit_user_profile', [$this, 'add_loyalty_level_field'], 5);
        add_action('personal_options_update', [$this, 'save_loyalty_level_field']);
        add_action('edit_user_profile_update', [$this, 'save_loyalty_level_field']);
    }

    /**
     * Adds a loyalty level field to the user profile page.
     *
     * @param \WP_User $user The user object.
     */
    public function add_loyalty_level_field($user) {
        $loyalty_level_3 = get_user_meta($user->ID, '_loyalty_level_3', true);
        ?>
        <h3><?php _e("Программа лояльности", "textdomain"); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="loyalty_level_3"><?php _e("Назначить уровень 3 программы лояльности", "textdomain"); ?></label></th>
                <td>
                    <input type="checkbox" name="loyalty_level_3" id="loyalty_level_3" value="1" <?php checked($loyalty_level_3, '1'); ?> />
                    <span class="description"><?php _e("Установите этот флажок, чтобы назначить уровень 3 этому клиенту.", "textdomain"); ?></span>
                </td>
            </tr>
        </table>
        <?php
    }

    
    
    /**
     * Saves the loyalty level field value.
     *
     * @param int $user_id The user ID.
     * @return bool
     */
    public function save_loyalty_level_field($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        // Sanitize and save the checkbox value
        $value = isset($_POST['loyalty_level_3']) ? '1' : '0';
        update_user_meta($user_id, '_loyalty_level_3', sanitize_text_field($value));
    }
}
