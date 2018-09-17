<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    block_quickmail
 * @copyright  2008 onwards Louisiana State University
 * @copyright  2008 onwards Chad Mazilly, Robert Russo, Jason Peak, Dave Elliott, Adam Zapletal, Philip Cali
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_quickmail\messenger\factories\course_recipient_send;

use block_quickmail\messenger\factories\course_recipient_send\recipient_send_factory;
use block_quickmail\messenger\factories\course_recipient_send\recipient_send_factory_interface;
use block_quickmail\persistents\alternate_email;
use block_quickmail_string;

class email_recipient_send_factory extends recipient_send_factory implements recipient_send_factory_interface {

    public function set_factory_params()
    {
        $this->message_params->attachment = '';
        $this->message_params->attachname = '';
        $this->message_params->wordwrapwidth = 79;
    }

    public function set_factory_computed_params()
    {
        $this->message_params->usetrueaddress = $this->should_use_true_address();
        $this->message_params->replyto = $this->get_replyto_email();
        $this->message_params->replytoname = $this->get_replyto_name();
        $this->alternate_email = alternate_email::find_or_null($this->message->get('alternate_email_id'));
    }

    /**
     * Executes the sending of this message to this recipient
     *
     * Additionally, if successful, handle any post send actions (marking as sent, sending to mentors if appropriate)
     * 
     * @return bool
     */
    public function send()
    {
        $success = $this->send_email_to_user();

        // if the message was sent successfully, handle post send tasks
        if ($success) {
            $this->handle_recipient_post_send();
        }
        
        return $success;
    }

    /**
     * Sends this formatted message content to the given user
     *
     * If no user is given, sends to this recipient user
     * 
     * @param  object  $user
     * @param  array   $options
     * @return bool
     */
    private function send_email_to_user($user = null, $options = [])
    {
        // if no user was specified, use the recipient user
        if (is_null($user)) {
            $user = $this->message_params->userto;
        }

        $success = email_to_user(
            $user,
            $this->message_params->userfrom,
            $this->get_subject_prefix($options) . $this->message_params->subject,
            $this->get_message_prefix($options) . $this->message_params->fullmessage,
            $this->get_message_prefix($options) . $this->message_params->fullmessagehtml,
            $this->message_params->attachment,
            $this->message_params->attachname,
            $this->message_params->usetrueaddress,
            $this->message_params->replyto,
            $this->message_params->replytoname,
            $this->message_params->wordwrapwidth
        );

        return $success;
    }

    /**
     * Sends a "mentor-formatted" email to the given mentor user
     *
     * @param  object  $mentor_user
     * @param  object  $mentee_user
     * @return bool
     */
    private function send_email_to_mentor_user($mentor_user, $mentee_user)
    {
        return $this->send_email_to_user($mentor_user, [
            'subject_prefix' => block_quickmail_string::get('mentor_copy_subject_prefix'),
            'message_prefix' => block_quickmail_string::get('mentor_copy_message_prefix', fullname($mentee_user))
        ]);
    }

    /**
     * Sends this formatted message to any existing mentors of this recipient user
     * 
     * @return void
     */
    public function send_to_mentors()
    {
        $mentor_users = $this->get_recipient_mentors();
        $prepended_mentor_users = $this->get_recipient_prepended_mentors();

        $mentee_user = $this->recipient->get_user();

        foreach ($mentor_users as $mentor_user) {
            $this->send_email_to_mentor_user($mentor_user, $mentee_user);
        }

        foreach ($prepended_mentor_users as $mentor_user) {
            $this->send_email_to_mentor_user($mentor_user, $mentee_user);
        }
    }

    private function should_use_true_address()
    {
        return $this->message->get('no_reply') || $this->message->get('alternate_email_id')
            ? false
            : true;
    }

    private function get_replyto_email()
    {
        // message is marked as "no reply"
        if ((bool) $this->message->get('no_reply')) {
            // return the default no reply address
            return get_config('moodle', 'noreplyaddress');
        }

        // if this message has an alternate email assigned
        if ($this->alternate_email) {
            // return the alternate's email address
            return $this->alternate_email->get('email');
        }

        // otherwise, return the moodle user's email
        return $this->message_params->userfrom->email;
    }

    private function get_replyto_name()
    {
        // message is marked as "no reply"
        if ((bool) $this->message->get('no_reply')) {
            // return the default no reply address
            return get_config('moodle', 'noreplyaddress');
        }

        // if this message has an alternate email assigned
        if ($this->alternate_email) {
            // return the alternate's full name
            return $this->alternate_email->get_fullname();
        }

        // otherwise, return the moodle user's full name
        return fullname($this->message_params->userfrom);
    }


}