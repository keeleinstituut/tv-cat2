<?php

namespace App\Services\Dto;


class UserPrivileges
{
    private $data;

    public function __construct($data) {
        $this->data = $data;
    }

    public function getRawData() {
        return $this->data;
    }

    public function getPrivilegesPerInstitution() {
        return $this->data['institutionPrivileges'];
    }

    public function getInstitutionsPerPrivilege() {
        return collect($this->getPrivilegesPerInstitution())
            ->reduce(function ($acc, $privileges, $key) {
                foreach ($privileges as $index => $privilege) {
                    $acc[$privilege] = array_merge($acc[$privilege] ?? [], [$key]);
                }
                return $acc;
            }, []);
    }

    public function getInstitutionsForPrivilege(string $privilege) {
        return $this->getInstitutionsPerPrivilege()[$privilege];
    }
}
