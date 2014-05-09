<?php

class GuestUser extends User
{

    public function __construct()
    {

    }

    public function isAdministrator()
    {
        return false;
    }

    public function isEditor()
    {
        return false;
    }

    public function getNJLPTLevel()
    {
        return 5;
    }

    public function isGuestUser()
    {
        return true;
    }

    public function isLoggedIn()
    {
        return false;
    }

    public function getJLPTNumLevel()
    {
        return 5;
    }

    public function getFbID()
    {
        return 0;
    }

    public function getID()
    {
        return -1;
    }

    public function geFirstName()
    {
        return 'Guest User';
    }

    public function getLevel()
    {
        return 5;
    }

    public function isElite()
    {
        return false;
    }
}
