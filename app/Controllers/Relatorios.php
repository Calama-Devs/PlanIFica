<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\AulaHorarioModel;
use App\Models\AmbientesModel;
use App\Models\CursosModel;
use App\Models\TurmasModel;
use App\Models\ProfessorModel;
use App\Models\GruposAmbientesModel;
use App\Models\VersoesModel;
use App\Models\AulasModel;

class Relatorios extends BaseController
{
    protected $aulaHorarioModel;
    protected $ambientesModel;
    protected $cursosModel;
    protected $turmasModel;
    protected $professorModel;
    protected $gruposAmbientesModel;
    protected $aulasModel;
    private $versao_nome;

    public function __construct()
    {
        $this->aulaHorarioModel = new AulaHorarioModel();
        $this->ambientesModel = new AmbientesModel();
        $this->cursosModel = new CursosModel();
        $this->turmasModel = new TurmasModel();
        $this->professorModel = new ProfessorModel();
        $this->gruposAmbientesModel = new GruposAmbientesModel();
        $this->aulasModel = new AulasModel();

        $this->versao_nome = '';
        $versaoModel = new VersoesModel();
        $versao = $versaoModel->getVersaoByUser(auth()->id());
        $versao = $versaoModel->find($versao);
        $this->versao_nome = $versao['nome'];
    }

    public function index()
    {
        $data = [
            'cursos' => $this->cursosModel->orderBy('nome')->findAll(),
            'professores' => $this->professorModel->orderBy('nome')->findAll(),
            'ambientes' => $this->ambientesModel->orderBy('nome')->findAll(),
            'gruposAmbientes' => $this->gruposAmbientesModel->orderBy('nome')->findAll(),
        ];

        $this->content_data['content'] = view('sys/relatorios', $data);
        return view('dashboard', $this->content_data);
    }

    public function getTurmasByCurso()
    {
        $cursos = $this->request->getPost('cursos');

        if (empty($cursos)) {
            return $this->response->setJSON([]);
        }

        $turmas = $this->turmasModel
            ->select('id, sigla as text')
            ->whereIn('curso_id', $cursos)
            ->orderBy('sigla', 'ASC')
            ->findAll();

        return $this->response->setJSON($turmas);
    }

    public function filtrar()
    {
        $tipo = $this->request->getPost('tipo');

        if (!$tipo) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Tipo de relatório não especificado'
            ]);
        }

        switch ($tipo) {
            case 'curso':
                $dados = $this->filtrarCursos();
                break;
            case 'professor':
                $dados = $this->filtrarProfessores();
                break;
            case 'ambiente':
                $dados = $this->filtrarAmbientes();
                break;
            default:
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Tipo de relatório inválido'
                ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $dados
        ]);
    }

    protected function filtrarCursos()
    {
        $cursos = $this->request->getPost('cursos') ?? [];
        $turmas = $this->request->getPost('turmas') ?? [];

        $builder = $this->aulaHorarioModel
            ->select('
                cursos.nome as curso,
                turmas.sigla as turma,
                disciplinas.nome as disciplina,
                professores.nome as professor,
                ambientes.nome as ambiente,
                tempos_de_aula.dia_semana,
                CONCAT(LPAD(tempos_de_aula.hora_inicio, 2, "0"), ":", LPAD(tempos_de_aula.minuto_inicio, 2, "0")) as hora_inicio,
                aula_horario.destaque as horario_destaque,
                aulas.destaque as aula_destaque
            ')
            ->join('aulas', 'aulas.id = aula_horario.aula_id')
            ->join('disciplinas', 'disciplinas.id = aulas.disciplina_id')
            ->join('turmas', 'turmas.id = aulas.turma_id')
            ->join('cursos', 'cursos.id = turmas.curso_id')
            ->join('aula_professor', 'aula_professor.aula_id = aulas.id')
            ->join('professores', 'professores.id = aula_professor.professor_id')
            ->join('aula_horario_ambiente', 'aula_horario_ambiente.aula_horario_id = aula_horario.id')
            ->join('ambientes', 'ambientes.id = aula_horario_ambiente.ambiente_id')
            ->join('tempos_de_aula', 'tempos_de_aula.id = aula_horario.tempo_de_aula_id')
            ->where('aula_horario.versao_id', (new \App\Models\VersoesModel())->getVersaoByUser(auth()->id()));

        if (!empty($cursos)) {
            $builder->whereIn('cursos.id', $cursos);
        }

        if (!empty($turmas)) {
            $builder->whereIn('turmas.id', $turmas);
        }

        $resultados = $builder->orderBy('cursos.nome')
            ->orderBy('turmas.sigla')
            ->orderBy('tempos_de_aula.dia_semana')
            ->orderBy('tempos_de_aula.hora_inicio')
            ->orderBy('tempos_de_aula.minuto_inicio')
            ->findAll();

        // Marcar como destaque se qualquer um dos campos for 1
        foreach ($resultados as &$resultado) {
            $resultado['destaque'] = ($resultado['horario_destaque'] == 1 || $resultado['aula_destaque'] == 1) ? 1 : 0;
            unset($resultado['horario_destaque']);
            unset($resultado['aula_destaque']);
        }

        return $resultados;
    }

    protected function filtrarProfessores()
    {
        $professores = $this->request->getPost('professores') ?? [];

        $builder = $this->aulaHorarioModel
            ->select('
                professores.nome as professor,
                cursos.nome as curso,
                turmas.sigla as turma,
                disciplinas.nome as disciplina,
                ambientes.nome as ambiente,
                tempos_de_aula.dia_semana,
                CONCAT(LPAD(tempos_de_aula.hora_inicio, 2, "0"), ":", LPAD(tempos_de_aula.minuto_inicio, 2, "0")) as hora_inicio,
                aula_horario.destaque as horario_destaque,
                aulas.destaque as aula_destaque
            ')
            ->join('aulas', 'aulas.id = aula_horario.aula_id')
            ->join('disciplinas', 'disciplinas.id = aulas.disciplina_id')
            ->join('turmas', 'turmas.id = aulas.turma_id')
            ->join('cursos', 'cursos.id = turmas.curso_id')
            ->join('aula_professor', 'aula_professor.aula_id = aulas.id')
            ->join('professores', 'professores.id = aula_professor.professor_id')
            ->join('aula_horario_ambiente', 'aula_horario_ambiente.aula_horario_id = aula_horario.id')
            ->join('ambientes', 'ambientes.id = aula_horario_ambiente.ambiente_id')
            ->join('tempos_de_aula', 'tempos_de_aula.id = aula_horario.tempo_de_aula_id')
            ->where('aula_horario.versao_id', (new \App\Models\VersoesModel())->getVersaoByUser(auth()->id()));

        if (!empty($professores)) {
            $builder->whereIn('professores.id', $professores);
        }

        $resultados = $builder->orderBy('professores.nome')
            ->orderBy('tempos_de_aula.dia_semana')
            ->orderBy('tempos_de_aula.hora_inicio')
            ->orderBy('tempos_de_aula.minuto_inicio')
            ->findAll();

        // Marcar como destaque se qualquer um dos campos for 1
        foreach ($resultados as &$resultado) {
            $resultado['destaque'] = ($resultado['horario_destaque'] == 1 || $resultado['aula_destaque'] == 1) ? 1 : 0;
            unset($resultado['horario_destaque']);
            unset($resultado['aula_destaque']);
        }

        return $resultados;
    }

    protected function filtrarAmbientes()
    {
        $ambientes = $this->request->getPost('ambientes') ?? [];
        $grupos = $this->request->getPost('grupos_ambientes') ?? [];

        $builder = $this->aulaHorarioModel
            ->select('
                ambientes.nome as ambiente,
                cursos.nome as curso,
                turmas.sigla as turma,
                disciplinas.nome as disciplina,
                professores.nome as professor,
                tempos_de_aula.dia_semana,
                CONCAT(LPAD(tempos_de_aula.hora_inicio, 2, "0"), ":", LPAD(tempos_de_aula.minuto_inicio, 2, "0")) as hora_inicio,
                CONCAT(LPAD(tempos_de_aula.hora_fim, 2, "0"), ":", LPAD(tempos_de_aula.minuto_fim, 2, "0")) as hora_fim,
                aula_horario.destaque as horario_destaque,
                aulas.destaque as aula_destaque
            ')
            ->join('aulas', 'aulas.id = aula_horario.aula_id')
            ->join('disciplinas', 'disciplinas.id = aulas.disciplina_id')
            ->join('turmas', 'turmas.id = aulas.turma_id')
            ->join('cursos', 'cursos.id = turmas.curso_id')
            ->join('aula_professor', 'aula_professor.aula_id = aulas.id')
            ->join('professores', 'professores.id = aula_professor.professor_id')
            ->join('aula_horario_ambiente', 'aula_horario_ambiente.aula_horario_id = aula_horario.id')
            ->join('ambientes', 'ambientes.id = aula_horario_ambiente.ambiente_id')
            ->join('tempos_de_aula', 'tempos_de_aula.id = aula_horario.tempo_de_aula_id')
            ->where('aula_horario.versao_id', (new \App\Models\VersoesModel())->getVersaoByUser(auth()->id()));

        if (!empty($grupos)) {
            $builder->join('ambiente_grupo', 'ambiente_grupo.ambiente_id = ambientes.id')
                ->whereIn('ambiente_grupo.grupo_de_ambiente_id', $grupos);
        }

        if (!empty($ambientes)) {
            $builder->whereIn('ambientes.id', $ambientes);
        }

        $resultados = $builder->orderBy('ambientes.nome')
            ->orderBy('tempos_de_aula.dia_semana')
            ->orderBy('tempos_de_aula.hora_inicio')
            ->orderBy('tempos_de_aula.minuto_inicio')
            ->findAll();

        // Marcar como destaque se qualquer um dos campos for 1
        foreach ($resultados as &$resultado) {
            $resultado['destaque'] = ($resultado['horario_destaque'] == 1 || $resultado['aula_destaque'] == 1) ? 1 : 0;
            unset($resultado['horario_destaque']);
            unset($resultado['aula_destaque']);
        }

        return $resultados;
    }

    public function exportar()
    {
        set_time_limit(520);
        $tipo = $this->request->getPost('tipoRelatorio');

        if (!$tipo) {
            die("Sem tipo selecionado.");
        }

        switch ($tipo) {
            case 'curso':
                $dados = $this->filtrarCursos();
                $this->exportarCursoTurma($dados);
                break;
            case 'professor':
                $dados = $this->filtrarProfessores();
                $this->exportarProfessor($dados);
                break;
            case 'ambiente':
                $dados = $this->filtrarAmbientes();
                $this->exportarAmbiente($dados);
                break;
            default:
                die("Tipo inválido.");
        }
    }

    public function exportarAmbiente($dados)
    {
        $tabelas = [];

        foreach ($dados as $key => $value) {
            if (!in_array($value['ambiente'], $tabelas)) {
                $tabelas[$value['ambiente']] = [];
            }
        }

        foreach ($dados as $key => $value) {
            for ($i = 1; $i <= 5; $i++) {
                if (!in_array($i, $tabelas[$value['ambiente']])) {
                    $tabelas[$value['ambiente']][$i] = [];
                }
            }
        }

        foreach ($dados as $key => $value) {
            if (!in_array($value['hora_inicio'], $tabelas[$value['ambiente']][$value['dia_semana']])) {
                $tabelas[$value['ambiente']][$value['dia_semana']][$value['hora_inicio']] = [];
            }
        }

        foreach ($dados as $key => $value) {
            if (empty($tabelas[$value['ambiente']][$value['dia_semana']][$value['hora_inicio']]['disciplina'])) {
                $tabelas[$value['ambiente']][$value['dia_semana']][$value['hora_inicio']]['professor'] = $value['professor'];
                $tabelas[$value['ambiente']][$value['dia_semana']][$value['hora_inicio']]['disciplina'] = $value['disciplina'];
                $tabelas[$value['ambiente']][$value['dia_semana']][$value['hora_inicio']]['curso'] = $value['curso'];
                $tabelas[$value['ambiente']][$value['dia_semana']][$value['hora_inicio']]['turma'] = $value['turma'];
                $tabelas[$value['ambiente']][$value['dia_semana']][$value['hora_inicio']]['destaque'] = $value['destaque'];
            } else {
                if (strpos($tabelas[$value['ambiente']][$value['dia_semana']][$value['hora_inicio']]['professor'], $value['professor']) === false) {
                    $tabelas[$value['ambiente']][$value['dia_semana']][$value['hora_inicio']]['professor'] .= ', ' . $value['professor'];
                }
            }
        }

        $pdf = new \App\Libraries\PDF();

        $pdf->setCSS('
            @page { margin: 8 !important; padding: 0 !important; margin-top: 65px !important; }
            body { font-family: Arial, sans-serif; font-size: 9px; padding: 10px; background: #fff; color: #000; }
            header { align-items: center; padding-bottom: 1px; margin-bottom: 3px; position: fixed; margin-top: -50px; width: 98%; }
            header img { height: 45px; margin-right: 10px; margin-left: 10px; }
            footer { position: fixed; bottom: -10px; left: 0px; right: 0px; height: 20px; }
            footer .pagenum:before { content: counter(page); }
            h1 { font-size: 11px; color:rgb(5, 56, 5); padding: 0px; margin: 0px; }
            h2 { font-size: 10px; color: #1a5d1a; padding: 0px; margin: 0px; }
            h3 { font-size: 10px; color: #1a5d1a; padding: 0px; margin: 0px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 6px; table-layout:fixed;  }
            .caption { font-size: 11px; font-weight: bold; background-color: #1a5d1a; color: white; padding: 1px; border-radius: 4px 4px 0 0; text-align: center; }
            .periodo { font-size: 10px; font-weight: bold; background-color: #1a5d1a; color: white; padding: 0px; text-align: center; border: none }
            th, td { border: 1px solid #ccc; padding: 1px; text-align: center; vertical-align: middle; }
            th { background-color: #d1e7d1; color: #1a5d1a; }
            tr:nth-child(even) td { background-color: #f5fdf5; }
            .hora { font-weight: bold; }
            em { font-style: normal;  display: block; margin-top: 1px; color: #3d7b3d; }
            .page_break { page-break-before: always; }
            .destaque { background-color: #ffcccc !important; }
        ');

        $pdf->setHeader('
            <table>
                <tr>
                    <td width="15%" style="border: none;"><img src="' . base_url("assets/images/logoifro.png") . '" alt="Logo IFRO"></td>
                    <td width="65%">
                        <h1>Instituto Federal de Educação, Ciência e Tecnologia de Rondônia</h1>
                        <h2><i>Campus</i> Porto Velho Calama</h2>
                        <h1>Horários por Ambiente</h1>
                    </td>
                    <td width="15%" style="text-align: center; border: none;">
                        <img style="height: 25px; margin-bottom: 2px;" src="' . base_url("assets/images/Planifica.png") . '" alt="Logo IFRO">
                        <h1>Horário ' . $this->versao_nome . '</h1>
                    </td>
                </tr>
            </table>');

        $nome_dia = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sabado'];

        $conta = 0;

        foreach ($tabelas as $ambiente => $dias) {
            $temDias = [1, 2, 3, 4, 5];
            $temHorarios = [];

            foreach ($dias as $dia => $horarios) {
                foreach ($horarios as $hora_inicio => $outros) {
                    if (in_array($hora_inicio, $temHorarios))
                        continue;

                    array_push($temHorarios, $hora_inicio);
                }
            }

            sort($temDias);
            sort($temHorarios);

            if ($conta > 0 && $conta < sizeof($tabelas))
                $pdf->appendHTML('<div class="page_break"></div>');

            $pdf->appendHTML('
                <table>
                    <thead>
                        <tr>
                            <td colspan="' . (sizeof($temDias) + 1) . '" style="border: none; padding: 0px; text-align: center;">
                                <div class="caption">' . $ambiente . '</div>
                            </td>
                        </tr>
                        <tr>
                            <th width="4%">Horário</th>');

            foreach ($temDias as $dia) {
                $pdf->appendHTML('
                    <th width="20%">' . $nome_dia[$dia] . '</th>
                ');
            }

            $pdf->appendHTML('</tr></thead><tbody>');

            $ultimoTurno = 0;

            foreach ($temHorarios as $horario) {
                $horarioAtual = (int)(substr($horario, 0, 2));
                $turnoAtual = ($horarioAtual <= 12) ? 1 : (($horarioAtual >= 13 && $horarioAtual <= 17) ? 2 : 3);

                if ($ultimoTurno == 0 || $turnoAtual != $ultimoTurno) {
                    if ($turnoAtual == 1)
                        $pdf->appendHTML('<tr><th colspan="' . (sizeof($temDias) + 1) . '" class="periodo">M A N H Ã</th></tr>');
                    else if ($turnoAtual == 2)
                        $pdf->appendHTML('<tr><th colspan="' . (sizeof($temDias) + 1) . '" class="periodo">T A R D E</th></tr>');
                    else
                        $pdf->appendHTML('<tr><th colspan="' . (sizeof($temDias) + 1) . '" class="periodo">N O I T E</th></tr>');
                }

                $ultimoTurno = $turnoAtual;

                $pdf->appendHTML('<tr>');
                $pdf->appendHTML('<td class="hora">' . $horario . '</td>');

                foreach ($temDias as $dia) {
                    if (isset($tabelas[$ambiente][$dia])) {
                        if (isset($tabelas[$ambiente][$dia][$horario])) {
                            $destaqueClass = (isset($tabelas[$ambiente][$dia][$horario]['destaque']) && $tabelas[$ambiente][$dia][$horario]['destaque'] == 1) ? ' class="destaque"' : '';
                            $pdf->appendHTML('<td' . $destaqueClass . '>');

                            $pdf->appendHTML('<small>');
                            $pdf->appendHTML('<strong>' . $tabelas[$ambiente][$dia][$horario]['disciplina'] . '</strong>');
                            $pdf->appendHTML('</small>');

                            $pdf->appendHTML('<br />');
                            $pdf->appendHTML('<em>');

                            $pdf->appendHTML('<small>');
                            $pdf->appendHTML($tabelas[$ambiente][$dia][$horario]['turma']);
                            $pdf->appendHTML('</small>');

                            $pdf->appendHTML('<br />');

                            $virgulas = substr_count($tabelas[$ambiente][$dia][$horario]['professor'], ",");

                            if ($virgulas >= 1) {
                                $professores = explode(", ", $tabelas[$ambiente][$dia][$horario]['professor']);
                                foreach ($professores as $k => $v) {
                                    $nomes = explode(" ", $v);
                                    $professores[$k] = $nomes[0];
                                    $professores[$k] .= " ";
                                    $professores[$k] .= (strlen($nomes[1]) > 3) ? $nomes[1] : $nomes[1] . " " . $nomes[2];
                                }
                                $tabelas[$ambiente][$dia][$horario]['professor'] = implode(", ", $professores);
                            }

                            $pdf->appendHTML('<small>');
                            $pdf->appendHTML('<strong>' . $tabelas[$ambiente][$dia][$horario]['professor'] . '</strong>');
                            $pdf->appendHTML('</small>');

                            $pdf->appendHTML('</em>');
                            $pdf->appendHTML('</td>');
                        } else {
                            $pdf->appendHTML('<td>—</td>');
                        }
                    }
                }

                $pdf->appendHTML('</tr>');
            }

            $pdf->appendHTML('
                    </tbody>
                </table>
            ');

            $conta++;
        }

        $pdf->setFooter('
            <table style="width: 100%">
                <tr>
                    <td style="text-align: left; font-size: 8px; border: none;">Versão ' . $this->versao_nome . '</td>
                    <td style="text-align: center; font-size: 8px; border: none;">Gerado em ' . date('d/m/Y H:i') . 'h</td>
                    <td style="text-align: right; font-size: 8px; border: none;">Página <span class="pagenum"></span></td>
                </tr>
            </table>');

        $pdf->generatePDF("horarios_por_ambiente");
    }

    public function exportarProfessor($dados)
    {
        $tabelas = [];

        foreach ($dados as $key => $value) {
            if (!in_array($value['professor'], $tabelas)) {
                $tabelas[$value['professor']] = [];
            }
        }

        foreach ($dados as $key => $value) {
            for ($i = 1; $i <= 5; $i++) {
                if (!in_array($i, $tabelas[$value['professor']])) {
                    $tabelas[$value['professor']][$i] = [];
                }
            }
        }

        $db = \Config\Database::connect();
        $query = $db->query("SELECT DISTINCT(CONCAT(LPAD(tempos_de_aula.hora_inicio, 2, '0'), ':', LPAD(tempos_de_aula.minuto_inicio, 2, '0'))) as res FROM `tempos_de_aula`");

        foreach ($dados as $key => $value) {
            foreach ($query->getResult('array') as $row) {
                if (!in_array($value['hora_inicio'], $tabelas[$value['professor']][$value['dia_semana']])) {
                    $tabelas[$value['professor']][$value['dia_semana']][$row['res']] = [];
                }
            }
        }

        foreach ($dados as $key => $value) {
            if (empty($tabelas[$value['professor']][$value['dia_semana']][$value['hora_inicio']]['disciplina'])) {
                $tabelas[$value['professor']][$value['dia_semana']][$value['hora_inicio']]['professor'] = $value['professor'];
                $tabelas[$value['professor']][$value['dia_semana']][$value['hora_inicio']]['disciplina'] = $value['disciplina'];
                $tabelas[$value['professor']][$value['dia_semana']][$value['hora_inicio']]['ambiente'] = $value['ambiente'];
                $tabelas[$value['professor']][$value['dia_semana']][$value['hora_inicio']]['curso'] = $value['curso'];
                $tabelas[$value['professor']][$value['dia_semana']][$value['hora_inicio']]['turma'] = $value['turma'];
                $tabelas[$value['professor']][$value['dia_semana']][$value['hora_inicio']]['destaque'] = $value['destaque'];
            } else {
                if ($tabelas[$value['professor']][$value['dia_semana']][$value['hora_inicio']]['professor'] != $value['professor']) {
                    $tabelas[$value['professor']][$value['dia_semana']][$value['hora_inicio']]['professor'] .= ', ' . $value['professor'];
                } else if ($tabelas[$value['professor']][$value['dia_semana']][$value['hora_inicio']]['ambiente'] != $value['ambiente']) {
                    $tabelas[$value['professor']][$value['dia_semana']][$value['hora_inicio']]['ambiente'] .= ', ' . $value['ambiente'];
                }
            }
        }

        $pdf = new \App\Libraries\PDF();

        $pdf->setCSS('
            @page { margin: 8 !important; padding: 0 !important; margin-top: 70px !important; }
            body { font-family: Arial, sans-serif; font-size: 9px; padding: 10px; background: #fff; color: #000; }
            header { align-items: center; padding-bottom: 1px; margin-bottom: 8px; position: fixed; margin-top: -65px; width: 98%; }
            header img { height: 50px; margin-right: 5px; margin-left: 5px; }
            footer { position: fixed; bottom: -10px; left: 0px; right: 0px; height: 20px; }
            footer .pagenum:before { content: counter(page); }
            h1 { font-size: 11px; color:rgb(5, 56, 5); padding: 0px; margin: 0px; }
            h2 { font-size: 11px; color: #1a5d1a; padding: 0px; margin: 0px; }
            h3 { font-size: 10px; color: #1a5d1a; padding: 0px; margin: 0px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 10px; table-layout:fixed; }
            .caption { font-size: 13px; font-weight: bold; background-color: #1a5d1a; color: white; padding: 2px; border-radius: 4px 4px 0 0; text-align: center; }
            .periodo { font-size: 10px; font-weight: bold; background-color: #1a5d1a; color: white; padding: 0px; text-align: center; border: none }
            th, td { border: 1px solid #ccc; padding: 2px; text-align: center; vertical-align: middle; }
            th { background-color: #d1e7d1; color: #1a5d1a; }
            tr:nth-child(even) td { background-color: #f5fdf5; }
            .hora { font-weight: bold; }
            em { font-style: normal;  display: block; margin-top: 1px; color: #3d7b3d; }
            .page_break { page-break-before: always; }
            .destaque { background-color: #ffcccc !important; }
        ');

        $pdf->setHeader('
            <table>
                <tr>
                    <td width="15%" style="border: none;"><img src="' . base_url("assets/images/logoifro.png") . '" alt="Logo IFRO"></td>
                    <td width="65%">
                        <h1>Instituto Federal de Educação, Ciência e Tecnologia de Rondônia</h1>
                        <h2><i>Campus</i> Porto Velho Calama</h2>
                        <h3>Departamento de Apoio ao Ensino - DAPE</h3>
                        <h1>Horários por Professor</h1>
                    </td>
                    <td width="15%" style="text-align: center; border: none;">
                        <img style="height: 25px; margin-bottom: 2px;" src="' . base_url("assets/images/Planifica.png") . '" alt="Logo IFRO">
                        <h1>Horário ' . $this->versao_nome . '</h1>
                    </td>
                </tr>
            </table>');

        $pdf->setFooter('
            <table style="width: 100%">
                <tr>
                    <td style="text-align: left; font-size: 8px; border: none;">Versão ' . $this->versao_nome . '</td>
                    <td style="text-align: center; font-size: 8px; border: none;">Gerado em ' . date('d/m/Y H:i') . 'h</td>
                    <td style="text-align: right; font-size: 8px; border: none;">Página <span class="pagenum"></span></td>
                </tr>
            </table>');

        $nome_dia = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sabado'];

        $conta = 0;

        foreach ($tabelas as $professor => $dias) {
            $temDias = [1, 2, 3, 4, 5];
            $temHorarios = [];

            foreach ($dias as $dia => $horarios) {
                foreach ($horarios as $hora_inicio => $outros) {
                    if (in_array($hora_inicio, $temHorarios))
                        continue;

                    array_push($temHorarios, $hora_inicio);
                }
            }

            sort($temDias);
            sort($temHorarios);

            $pdf->appendHTML('
                <table>
                    <thead>
                        <tr>
                            <td colspan="' . (sizeof($temDias) + 1) . '" style="border: none; padding: 0px; text-align: center;">
                                <div class="caption">' . $professor . '</div>
                            </td>
                        </tr>
                        <tr>
                            <th width="4%">Horário</th>');

            foreach ($temDias as $dia) {
                $pdf->appendHTML('
                    <th width="20%">' . $nome_dia[$dia] . '</th>
                ');
            }

            $pdf->appendHTML('</tr></thead><tbody>');

            $ultimoTurno = 0;

            foreach ($temHorarios as $horario) {
                $horarioAtual = (int)(substr($horario, 0, 2));
                $turnoAtual = ($horarioAtual <= 12) ? 1 : (($horarioAtual >= 13 && $horarioAtual <= 17) ? 2 : 3);

                if ($ultimoTurno == 0 || $turnoAtual != $ultimoTurno) {
                    if ($turnoAtual == 1)
                        $pdf->appendHTML('<tr><th colspan="' . (sizeof($temDias) + 1) . '" class="periodo">M A N H Ã</th></tr>');
                    else if ($turnoAtual == 2)
                        $pdf->appendHTML('<tr><th colspan="' . (sizeof($temDias) + 1) . '" class="periodo">T A R D E</th></tr>');
                    else
                        $pdf->appendHTML('<tr><th colspan="' . (sizeof($temDias) + 1) . '" class="periodo">N O I T E</th></tr>');
                }

                $ultimoTurno = $turnoAtual;

                $pdf->appendHTML('<tr>');
                $pdf->appendHTML('<td class="hora">' . $horario . '</td>');

                foreach ($temDias as $dia) {
                    if (isset($tabelas[$professor][$dia])) {
                        if (isset($tabelas[$professor][$dia][$horario]) && isset($tabelas[$professor][$dia][$horario]['disciplina'])) {
                            $destaqueClass = (isset($tabelas[$professor][$dia][$horario]['destaque']) && $tabelas[$professor][$dia][$horario]['destaque'] == 1) ? ' class="destaque"' : '';
                            $pdf->appendHTML('<td' . $destaqueClass . '>');

                            if (strlen($tabelas[$professor][$dia][$horario]['disciplina']) >= 40)
                                $pdf->appendHTML('<small>');

                            $pdf->appendHTML('<strong>' . $tabelas[$professor][$dia][$horario]['disciplina'] . '</strong>');

                            if (strlen($tabelas[$professor][$dia][$horario]['disciplina']) >= 40)
                                $pdf->appendHTML('</small>');

                            $pdf->appendHTML('<br />');

                            $pdf->appendHTML('<em>');

                            if (strlen($tabelas[$professor][$dia][$horario]['turma']) >= 40)
                                $pdf->appendHTML('<small>');

                            $pdf->appendHTML($tabelas[$professor][$dia][$horario]['turma']);

                            if (strlen($tabelas[$professor][$dia][$horario]['turma']) >= 40)
                                $pdf->appendHTML('</small>');

                            $pdf->appendHTML('<br />');

                            if (strlen($tabelas[$professor][$dia][$horario]['ambiente']) >= 40)
                                $pdf->appendHTML('<small>');

                            $pdf->appendHTML('<strong>' . $tabelas[$professor][$dia][$horario]['ambiente'] . '</strong>');

                            if (strlen($tabelas[$professor][$dia][$horario]['ambiente']) >= 40)
                                $pdf->appendHTML('</small>');

                            $pdf->appendHTML('</em>');
                            $pdf->appendHTML('</td>');
                        } else {
                            $pdf->appendHTML('<td>—</td>');
                        }
                    }
                }

                $pdf->appendHTML('</tr>');
            }

            $pdf->appendHTML('
                    </tbody>
                </table>
            ');

            $conta++;

            if ($conta < sizeof($tabelas))
                $pdf->appendHTML('<div class="page_break"></div>');
        }

        $pdf->generatePDF("horarios_por_professor");
    }

    public function exportarCursoTurma($dados)
    {
        $tabelas = [];

        foreach ($dados as $key => $value) {
            if (!in_array($value['curso'], $tabelas)) {
                $tabelas[$value['curso']] = [];
            }
        }

        foreach ($dados as $key => $value) {
            if (!in_array($value['turma'], $tabelas[$value['curso']])) {
                $tabelas[$value['curso']][$value['turma']] = [];
            }
        }

        foreach ($dados as $key => $value) {
            for ($i = 1; $i <= 5; $i++) {
                if (!in_array($i, $tabelas[$value['curso']][$value['turma']])) {
                    $tabelas[$value['curso']][$value['turma']][$i] = [];
                }
            }
        }

        foreach ($dados as $key => $value) {
            if (!in_array($value['hora_inicio'], $tabelas[$value['curso']][$value['turma']][$value['dia_semana']])) {
                $tabelas[$value['curso']][$value['turma']][$value['dia_semana']][$value['hora_inicio']] = [];
            }
        }

        foreach ($dados as $key => $value) {
            if (empty($tabelas[$value['curso']][$value['turma']][$value['dia_semana']][$value['hora_inicio']]['disciplina'])) {
                $tabelas[$value['curso']][$value['turma']][$value['dia_semana']][$value['hora_inicio']]['disciplina'] = $value['disciplina'];
                $tabelas[$value['curso']][$value['turma']][$value['dia_semana']][$value['hora_inicio']]['professor'] = $value['professor'];
                $tabelas[$value['curso']][$value['turma']][$value['dia_semana']][$value['hora_inicio']]['ambiente'] = $value['ambiente'];
                $tabelas[$value['curso']][$value['turma']][$value['dia_semana']][$value['hora_inicio']]['destaque'] = $value['destaque'];
            } else {
                if (strpos($tabelas[$value['curso']][$value['turma']][$value['dia_semana']][$value['hora_inicio']]['professor'], $value['professor']) === false) {
                    $tabelas[$value['curso']][$value['turma']][$value['dia_semana']][$value['hora_inicio']]['professor'] .= ', ' . $value['professor'];
                }

                if (strpos($tabelas[$value['curso']][$value['turma']][$value['dia_semana']][$value['hora_inicio']]['ambiente'], $value['ambiente']) === false) {
                    $tabelas[$value['curso']][$value['turma']][$value['dia_semana']][$value['hora_inicio']]['ambiente'] .= ', ' . $value['ambiente'];
                }
            }
        }

        $pdf = new \App\Libraries\PDF();

        $pdf->setCSS('
            @page { margin: 10 !important; padding: 0 !important; margin-top: 100px !important; }
            body { font-family: Arial, sans-serif; font-size: 9px; padding: 10px; background: #fff; color: #000; }
            header { align-items: center; padding-bottom: 2px; margin-bottom: 10px; position: fixed; margin-top: -80px; width: 98%; }
            header img { height: 50px; margin-right: 10px; margin-left: 10px; }
            footer { position: fixed; bottom: -10px; left: 0px; right: 0px; height: 20px; }
            footer .pagenum:before { content: counter(page); }
            h1 { font-size: 13px; color:rgb(5, 56, 5); padding: 0px; margin: 0px; }
            h2 { font-size: 12px; color: #1a5d1a; padding: 0px; margin: 0px; }
            h3 { font-size: 11px; color: #1a5d1a; padding: 0px; margin: 0px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 10px; table-layout:fixed; }
            .caption { font-size: 13px; font-weight: bold; background-color: #1a5d1a; color: white; padding: 2px; border-radius: 4px 4px 0 0; text-align: center; }
            .periodo { font-size: 10px; font-weight: bold; background-color: #1a5d1a; color: white; padding: 0px; text-align: center; border: none }
            th, td { border: 1px solid #ccc; padding: 4px; text-align: center; vertical-align: middle; }
            th { background-color: #d1e7d1; color: #1a5d1a; }
            tr:nth-child(even) td { background-color: #f5fdf5; }
            .hora { font-weight: bold; }
            em { font-style: normal;  display: block; margin-top: 1px; color: #3d7b3d; }
            .page_break { page-break-before: always; }
            .destaque { background-color: #ffcccc !important; }
        ');

        $pdf->setHeader('
            <table>
                <tr>
                    <td width="15%" style="border: none;"><img src="' . base_url("assets/images/logoifro.png") . '" alt="Logo IFRO"></td>
                    <td width="65%">
                        <h1>Instituto Federal de Educação, Ciência e Tecnologia de Rondônia</h1>
                        <h2><i>Campus</i> Porto Velho Calama</h2>
                        <h3>Departamento de Apoio ao Ensino - DAPE</h3>
                        <h1>Horários por Curso e Turma</h1>
                    </td>
                    <td width="15%" style="text-align: center; border: none;">
                        <img style="height: 25px; margin-bottom: 2px;" src="' . base_url("assets/images/Planifica.png") . '" alt="Logo IFRO">
                        <h1>Horário ' . $this->versao_nome . '</h1>
                    </td>
                </tr>
            </table>');

        $pdf->setFooter('
            <table style="width: 100%">
                <tr>
                    <td style="text-align: left; font-size: 8px; border: none;">Versão ' . $this->versao_nome . '</td>
                    <td style="text-align: center; font-size: 8px; border: none;">Gerado em ' . date('d/m/Y H:i') . 'h</td>
                    <td style="text-align: right; font-size: 8px; border: none;">Página <span class="pagenum"></span></td>
                </tr>
            </table>');

        $nome_dia = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sabado'];

        $conta = 0;
        $total = 0;
        foreach ($tabelas as $curso => $turmas)
            $total += sizeof($turmas);

        foreach ($tabelas as $curso => $turmas) {
            foreach ($turmas as $turma => $dias) {
                $temDias = [];
                $temHorarios = [];

                foreach ($dias as $dia => $horarios) {
                    if (!in_array($dia, $temDias))
                        array_push($temDias, $dia);

                    foreach ($horarios as $hora_inicio => $outros) {
                        if (in_array($hora_inicio, $temHorarios))
                            continue;

                        array_push($temHorarios, $hora_inicio);
                    }
                }

                sort($temDias);
                sort($temHorarios);

                $pdf->appendHTML('
                    <table>
                        <thead>
                            <tr>
                                <td colspan="' . (sizeof($temDias) + 1) . '" style="border: none; padding: 0px; text-align: center;">
                                    <div class="caption">' . $curso . ' - ' . $turma . '</div>
                                </td>
                            </tr>
                            <tr>
                                <th width="4%">Horário</th>');

                foreach ($temDias as $dia) {
                    $pdf->appendHTML('
                        <th width="20%">' . $nome_dia[$dia] . '</th>
                    ');
                }

                $pdf->appendHTML('</tr></thead><tbody>');

                $ultimoTurno = 0;

                foreach ($temHorarios as $horario) {
                    $horarioAtual = (int)(substr($horario, 0, 2));
                    $turnoAtual = ($horarioAtual <= 12) ? 1 : (($horarioAtual >= 13 && $horarioAtual <= 17) ? 2 : 3);

                    if ($ultimoTurno == 0 || $turnoAtual != $ultimoTurno) {
                        if ($turnoAtual == 1)
                            $pdf->appendHTML('<tr><th colspan="' . (sizeof($temDias) + 1) . '" class="periodo">M A N H Ã</th></tr>');
                        else if ($turnoAtual == 2)
                            $pdf->appendHTML('<tr><th colspan="' . (sizeof($temDias) + 1) . '" class="periodo">T A R D E</th></tr>');
                        else
                            $pdf->appendHTML('<tr><th colspan="' . (sizeof($temDias) + 1) . '" class="periodo">N O I T E</th></tr>');
                    }

                    $ultimoTurno = $turnoAtual;

                    $pdf->appendHTML('<tr>');
                    $pdf->appendHTML('<td class="hora">' . $horario . '</td>');

                    foreach ($temDias as $dia) {
                        if (isset($tabelas[$curso][$turma][$dia])) {
                            if (isset($tabelas[$curso][$turma][$dia][$horario])) {
                                $destaqueClass = (isset($tabelas[$curso][$turma][$dia][$horario]['destaque']) && $tabelas[$curso][$turma][$dia][$horario]['destaque'] == 1) ? ' class="destaque"' : '';
                                $pdf->appendHTML('<td' . $destaqueClass . '>');

                                if (strlen($tabelas[$curso][$turma][$dia][$horario]['disciplina']) >= 40)
                                    $pdf->appendHTML('<small>');

                                $pdf->appendHTML('<strong>' . $tabelas[$curso][$turma][$dia][$horario]['disciplina'] . '</strong>');

                                if (strlen($tabelas[$curso][$turma][$dia][$horario]['disciplina']) >= 40)
                                    $pdf->appendHTML('</small>');

                                $pdf->appendHTML('<br />');
                                $pdf->appendHTML('<em>');

                                $virgulas = substr_count($tabelas[$curso][$turma][$dia][$horario]['professor'], ",");

                                if ($virgulas >= 1) {
                                    $professores = explode(", ", $tabelas[$curso][$turma][$dia][$horario]['professor']);
                                    foreach ($professores as $k => $v) {
                                        $nomes = explode(" ", $v);
                                        $professores[$k] = $nomes[0];
                                        $professores[$k] .= " ";
                                        $professores[$k] .= (strlen($nomes[1]) > 3) ? $nomes[1] : $nomes[1] . " " . $nomes[2];
                                    }
                                    $tabelas[$curso][$turma][$dia][$horario]['professor'] = implode(", ", $professores);
                                }

                                if (strlen($tabelas[$curso][$turma][$dia][$horario]['professor']) >= 40)
                                    $pdf->appendHTML('<small>');

                                $pdf->appendHTML($tabelas[$curso][$turma][$dia][$horario]['professor']);

                                if (strlen($tabelas[$curso][$turma][$dia][$horario]['professor']) >= 40)
                                    $pdf->appendHTML('</small>');

                                $pdf->appendHTML('<br />');

                                if (strlen($tabelas[$curso][$turma][$dia][$horario]['ambiente']) >= 40)
                                    $pdf->appendHTML('<small>');

                                $pdf->appendHTML('<strong>' . $tabelas[$curso][$turma][$dia][$horario]['ambiente'] . '</strong>');

                                if (strlen($tabelas[$curso][$turma][$dia][$horario]['ambiente']) >= 40)
                                    $pdf->appendHTML('</small>');

                                $pdf->appendHTML('</em>');
                                $pdf->appendHTML('</td>');
                            } else {
                                $pdf->appendHTML('<td>—</td>');
                            }
                        }
                    }

                    $pdf->appendHTML('</tr>');
                }

                $pdf->appendHTML('
                        </tbody>
                    </table>
                ');

                $conta++;

                if ($conta < $total)
                    $pdf->appendHTML('<div class="page_break"></div>');
            }
        }

        $pdf->generatePDF("horarios_por_curso");
    }

    public function getAmbientesByGrupo()
    {
        $grupos = $this->request->getPost('grupos');

        if (empty($grupos)) {
            return $this->response->setJSON([]);
        }

        $ambientes = $this->ambientesModel
            ->select('ambientes.id, ambientes.nome')
            ->join('ambiente_grupo', 'ambiente_grupo.ambiente_id = ambientes.id')
            ->whereIn('ambiente_grupo.grupo_de_ambiente_id', $grupos)
            ->orderBy('ambientes.nome', 'ASC')
            ->findAll();

        return $this->response->setJSON($ambientes);
    }
}
