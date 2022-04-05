<?php

namespace VisualMetrics\HSU\Core\Extensions;

use SilverStripe\ORM\DataExtension;

class Tokenator extends DataExtension
{
    private static $tokenator_field;
    private static $tokenator_char_length;
    private static $tokenator_allow_multicase = false;

    public static function generate_tokenator(?int $charLength = null)
    {
        if (empty($charLength)) {
            $charLength = 12;
        }
        $token = '';
        $codeAlphabet = 'abcdefghijklmnopqrstuvwxyz';
        $codeAlphabet .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $codeAlphabet .= '0123456789';
        $max = strlen($codeAlphabet);
        for ($i = 0; $i < $charLength; $i++) {
            $token .= $codeAlphabet[random_int(0, $max - 1)];
        }
        return $token;
    }

    public function refreshTokenator()
    {
        $tokenatorField = $this->getOwner()->config()->get('tokenator_field');
        if (!empty($tokenatorField))
        {
            $charLength = (int) $this->getOwner()->config()->get('tokenator_char_length');
            if ($charLength < 1) {
                $charLength = null;
            }
            $token = self::generate_tokenator($charLength);
            if (!$this->getOwner()->config()->get('tokenator_allow_multicase')) {
                $token = strtolower($token);
            }

            if ($this->getOwner()->hasMethod('getTokenatorScope')) {
                $scope = $this->getOwner()->getTokenatorScope();
                $scope->filter($tokenatorField, $token);
            }
            else {
                $class = get_class($this->getOwner());
                $scope = $class::get()->filter($tokenatorField, $token);
                if ($this->getOwner()->isInDB()) {
                    $scope = $scope->exclude('ID', $this->getOwner()->ID);
                }
            }

            if ($scope->count() > 0) {
                return $this->getOwner()->refreshTokenator();
            }

            $this->getOwner()->{$tokenatorField} = $token;
        }
        return $this->getOwner();
    }

    public function onBeforeWrite()
    {
        $tokenatorField = $this->getOwner()->config()->get('tokenator_field');
        if (!empty($tokenatorField) && empty($this->getOwner()->{$tokenatorField})) {
            $this->getOwner()->refreshTokenator();
        }
    }
}
