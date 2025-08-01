<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\AulaHorarioModel;

class AulaHorarioController extends BaseController
{
  public function verificarConflitosRotina() {

   $aulaHorarioModel = new AulaHorarioModel();
    
   

   $aulas = $aulaHorarioModel
   ->where('fixa =', null)
   ->findAll();
   
   $conflitos = [];
    //construindo o array de conflitos com o retorno das verificações
    foreach ($aulas as $aula) {
        $id = $aula['id'];

        $conflitoAmbiente = $aulaHorarioModel->choqueAmbiente($id);
        if ($conflitoAmbiente > 0) {
            $conflitos[] = [
                'aula_horario_id' => $id,
                'tipo' => 'CONFLITO-AMBIENTE',
                'referencia' => $conflitoAmbiente
            ];
            continue;
        }

        $conflitoDocente = $aulaHorarioModel->choqueDocente($id);
        if ($conflitoDocente > 0) {
            $conflitos[] = [
             'aula_horario_id' => $id,
             'tipo' => 'CONFLITO-PROFESSOR',
             'referencia' => $conflitoDocente
            ];
        }

        $conflitoTurno = $aulaHorarioModel->verificarTresTurnos($id);
        if($conflitoTurno > 0) {
         $conflitos[] = [
           'aula_horario_id' => $id,
           'tipo' => 'CONFLITO-TURNOS',
           'referencia' => $conflitoTurno
         ];
        }

        $conflitoDocenteRestricao = $aulaHorarioModel->restricaoDocente($id);
        if($conflitoDocenteRestricao > 0) {
          $conflitos[] = [
           'aula_horario_id' => $id,
           'tipo' => 'RESTRIÇÃO-DOCENTE',
           'referencia' => $conflitoDocenteRestricao,
          ];
        }

        $conflitoIntervalo = $aulaHorarioModel->verificarTempoEntreTurnos($id);
        if($conflitoIntervalo > 0){
          $conflitos[] = [
           'aula_horario_id' => $id,
           'tipo' => 'CONFLITO-INTERVALO',
           'referencia' => $conflitoIntervalo,
          ];

        }

    }
    // dd($conflitos);
    return $this->response->setJSON($conflitos);


  }
}

// $intervalo = $aulaHorarioModel->verificarTempoEntreTurnos($aulaHorarioId);

// if ($intervalo > 0)
// {
//     echo "$intervalo-INTERVALO";
//     return;
// }