<?php

namespace App\Interfaces;

interface UserRepositoryInterface
{
    public function createAnonymousUser(array $data);
    public function findByFcmToken($fcmToken);
    public function updateLanguage($userId, $languageCode);
}
