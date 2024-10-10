<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

use App\Models\ProfessorModel;

class Professor extends BaseController
{
    public function index()
    {
    }

    public function cadastro(): string
    {
        // Exibe o formulário de cadastro
        $data['content'] = view('sys/cadastro-professor');
        return view('dashboard', $data);
    }

    public function salvar(): ResponseInterface
    {
        $professor = new \App\Models\ProfessorModel();

        if($professor->insert($this->request->getPost())){
            echo'inserido com sucesso';
        }else{
            print_r($professor->errors()) ;
        }

        return redirect()->to(uri: '/sys/professor/cadastro');
    } 
}
