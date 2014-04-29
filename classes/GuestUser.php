<?php

class GuestUser extends User {

    function __construct() {
        
    }

    function isAdministrator() {
        return false;
    }

    function isEditor() {
        return false;
    }

    function get_njlpt_level() {
        return 5;
    }

    function is_guest_user() {
        return true;
    }

    function is_logged_in() {
        return false;
    }

    function getJLPTNumLevel() {
        return 5;
    }

    function get_fb_id() {
        return 0;
    }

    function getID() {
        return -1;
    }

    function get_first_name() {
        return 'Guest User';
    }

    function getLevel() {
        return 5;
    }

    function is_elite() {
        return false;
    }

}
