<?php


class EmailOverrideForSubaccounts
{
    public function __construct()
    {
        // Hook into wp_mail to override email addresses for subaccounts and modify the subject
        add_filter('wp_mail', [$this, 'override_subaccount_email']);
    }

    public function override_subaccount_email($email_args)
    {
        // Get the recipient email
        $to_email = $email_args['to'];

        // Check if the recipient is a subaccount by looking up the fake email
        $user = get_user_by('email', $to_email);
        if ($user) {
            // Check if this user has a master email in meta (indicating it's a subaccount)
            $master_email = get_user_meta($user->ID, 'master_email', true);
            if ($master_email) {
                // Replace the fake email with the master email
                $email_args['to'] = $master_email;

                // Add the subaccount's username to the subject
                $username = $user->user_login;
                $email_args['subject'] = '[Order for ' . $username . '] ' . $email_args['subject'];
            }
        }

        return $email_args;
    }
}

// Instantiate the class to activate the hook
new EmailOverrideForSubaccounts();


// Instantiate the class to activate the hook
new EmailOverrideForSubaccounts();

