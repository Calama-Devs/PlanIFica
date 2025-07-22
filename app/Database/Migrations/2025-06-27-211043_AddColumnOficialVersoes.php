<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddColumnOficialVersoes extends Migration
{
    public function up()
    {
        $fields = [
            'oficial' => [
                'type'              => 'TINYINT', 
                'constraint'        => 1, 
                'null'              => TRUE, 
                'default'           => 0
            ]
        ];

        $this->forge->addColumn('versoes', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('versoes', 'oficial');
    }
}
