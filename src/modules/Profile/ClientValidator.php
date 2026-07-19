<?php

declare(strict_types=1);

namespace Box\Mod\Profile;

use Box\Mod\Client\Entity\Client;
use FOSSBilling\InformationException;

class ClientValidator
{
    /**
     * Validate and normalize gender.
     */
    public static function validateGender(?string $gender): ?string
    {
        if ($gender === null || trim($gender) === '') {
            return null;
        }
        $gender = strtolower(trim($gender));

        if (!in_array($gender, Client::ALLOWED_GENDERS, true)) {
            throw new InformationException('Invalid gender value. Allowed: male, female, nonbinary, other');
        }

        return $gender;
    }

    /**
     * Validate and normalize birthday.
     * Ensures it's a valid date, not older than 120 years, not in the future.
     */
    public static function validateBirthday(?string $birthday): ?string
    {
        if ($birthday === null || trim($birthday) === '') {
            return null;
        }

        if (strtotime($birthday) === false) {
            throw new InformationException('Invalid birthdate value');
        }

        $birthdayDate = new \DateTime($birthday);
        $today = new \DateTime('today');
        $minDate = (new \DateTime('today'))->modify('-120 years');

        if ($birthdayDate < $minDate) {
            throw new InformationException('Birthdate cannot be more than 120 years ago.');
        }

        if ($birthdayDate > $today) {
            throw new InformationException('Birthdate cannot be in the future.');
        }

        return $birthdayDate->format('Y-m-d');
    }
}
