<?php

namespace Aditum\Payment\Helper;

class DbPIX {

    protected $connection;

    public function __construct(\Magento\Framework\App\ResourceConnection $resource)
    {
        $this->connection = $resource->getConnection();
    }
    public function updateToken($expires_in,$token){
        $sql = "UPDATE pix_config SET pix_value = '" . $expires_in . "'WHERE pix_option = 'token_expires'";
        $this->connection->query($sql);
        $sql = "UPDATE pix_config SET  pix_value = '" . $token . "' WHERE pix_option = 'token_value'";
        $this->connection->query($sql);
    }
    public function getToken(){
        $sql = "SELECT pix_value FROM pix_config WHERE pix_option = 'token_expires'";
        $token_expires = $this->connection->fetchOne($sql);
        $sql = "SELECT pix_value FROM pix_config WHERE pix_option = 'token_value'";
        if (time() + 600 < $token_expires) {
            $token = $this->connection->fetchOne($sql);
            return $token;
        }
        return false;
    }
}

