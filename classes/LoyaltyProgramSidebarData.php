<?php

namespace SilkyDrum\WooCommerce;
class LoyaltyProgramSidebarData
{
    /**
     * Retrieve the loyalty data for a specific user.
     *
     * @param int $user_id The ID of the user.
     * @return array The loyalty data containing the level and other relevant information.
     */
    public function get_loyalty_data($user_id)
    {
        // Fetch the loyalty level from user meta, default to 0 if not set
        $loyalty_level = (int) get_user_meta($user_id, '_loyalty_level', true) ?: 0;

        // Dummy data for progress and discounts based on loyalty level
        $next_level = $loyalty_level + 1;
        $discounts = $this->get_discount_structure($loyalty_level);

        // Example progress data to the next level
        $progress = [
            'months_left' => 0,  // Dynamically calculate in actual use
            'orders_needed' => 1
        ];

        return [
            'loyalty_level' => $loyalty_level,
            'discounts' => $discounts,
            'next_level' => $next_level,
            'progress' => $progress
        ];
    }

    /**
     * Returns the discount structure based on the loyalty level.
     *
     * @param int $loyalty_level The loyalty level of the user.
     * @return array The discount data based on level.
     */
    private function get_discount_structure($loyalty_level)
    {
        // Define the discount structure based on loyalty levels
        return [
            1 => [
                'espresso' => ['200gr' => 6, '1kg' => 30],
                'filtr' => ['200gr' => 20, '1kg' => 100]
            ],
            2 => [
                'espresso' => ['200gr' => 10, '1kg' => 60],
                'filtr' => ['200gr' => 40, '1kg' => 200]
            ],
            3 => [
                'espresso' => ['200gr' => 20, '1kg' => 100],
                'filtr' => ['200gr' => 60, '1kg' => 300]
            ],
        ][$loyalty_level] ?? [];
    }
}

