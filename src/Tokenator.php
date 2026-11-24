<?php

namespace Fromholdio\Tokenator;

use LogicException;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;

class Tokenator extends Extension
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

            $scope = $this->getCurrentTokenatorScope()->filter($tokenatorField, $token);
            if ($this->getOwner()->isInDB()) {
                $scope = $scope->exclude('ID', $this->getOwner()->ID);
            }

            if ($scope->count() > 0) {
                return $this->getOwner()->refreshTokenator();
            }

            $this->getOwner()->{$tokenatorField} = $token;
        }
        return $this->getOwner();
    }

    /**
     * The records a new token must not clash with: the owner's getTokenatorScope()
     * when it defines one, otherwise every record of the owner's class. A hook
     * must return a DataList on the owner's base table, so that excluding the
     * owner by ID excludes the owner and nothing else.
     */
    protected function getCurrentTokenatorScope(): DataList
    {
        $owner = $this->getOwner();
        $class = get_class($owner);
        if (!$owner->hasMethod('getTokenatorScope')) {
            return $class::get();
        }
        $scope = $owner->getTokenatorScope();
        $schema = DataObject::getSchema();
        if (!$scope instanceof DataList
            || $schema->baseDataClass($scope->dataClass()) !== $schema->baseDataClass($class)
        ) {
            throw new LogicException($class . '::getTokenatorScope() must return a DataList on its own base table.');
        }
        return $scope;
    }

    public function onBeforeWrite()
    {
        $tokenatorField = $this->getOwner()->config()->get('tokenator_field');
        if (!empty($tokenatorField) && empty($this->getOwner()->{$tokenatorField})) {
            $this->getOwner()->refreshTokenator();
        }
    }
}
