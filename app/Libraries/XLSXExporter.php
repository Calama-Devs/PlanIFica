<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class XLSXExporter
{
    public function generateTimetable(string $reportTitle, array $groupedData, array $cellKeys, string $fileName, string $versionName)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Horários');

        $diasDaSemana = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta'];
        $colunasDias = [1 => 'B', 2 => 'C', 3 => 'D', 4 => 'E', 5 => 'F'];
        $ultimaColuna = 'F';

        // Variável para controlar a linha
        $currentRow = 1;

        // Loop para cada grupo de dados
        foreach ($groupedData as $groupName => $schedule) {
            if ($currentRow > 1) {
                $sheet->setBreak('A' . ($currentRow - 1), Worksheet::BREAK_ROW);
            }

            //cabeçalho-principal
            $this->drawHeader($sheet, $currentRow, $reportTitle, $versionName);
            $currentRow += 2;

            //cabeçalho-grupo
            $this->drawGroupHeader($sheet, $currentRow, $groupName, $ultimaColuna);
            $currentRow++;

            //cabeçalho-dias
            $this->drawDaysHeader($sheet, $currentRow, $diasDaSemana, $colunasDias, $ultimaColuna);
            $currentRow++;

            //grade de horarios
            $this->drawTimetableGrid($sheet, $currentRow, $schedule, $diasDaSemana, $colunasDias, $cellKeys);
            
            $currentRow += 2;
        }

        $sheet->getColumnDimension('A')->setWidth(15);
        foreach (range('B', 'F') as $columnID) {
            $sheet->getColumnDimension($columnID)->setWidth(25);
        }

        //geração do arquivo
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . urlencode($fileName . '.xlsx') . '"');
        header('Cache-Control: max-age=0');
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit();
    }

    private function drawHeader(Worksheet &$sheet, int &$currentRow, string $reportTitle, string $versionName): void
    {
        $sheet->getRowDimension($currentRow)->setRowHeight(45);

        // Logo IFRO
        $sheet->mergeCells("A{$currentRow}:A" . ($currentRow + 1));
        $logoEsquerdo = new Drawing();
        $logoEsquerdo->setPath(FCPATH . 'assets/images/logoifro.png')
            ->setCoordinates('A' . $currentRow)
            ->setHeight(45)
            ->setOffsetX(25)
            ->setOffsetY(10)
            ->setWorksheet($sheet);

        // Título Central
        $sheet->mergeCells("B{$currentRow}:E" . ($currentRow + 1));
        $tituloTexto = "Instituto Federal de Educação, Ciência e Tecnologia de Rondônia\nCampus Porto Velho Calama\nDepartamento de Apoio ao Ensino - DAPE\n{$reportTitle}";
        $sheet->getCell('B' . $currentRow)->setValue($tituloTexto);
        $style = $sheet->getStyle('B' . $currentRow);
        $style->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $style->getFont()->setBold(true);

        // Logo Planifica
        $sheet->mergeCells("F{$currentRow}:F" . ($currentRow + 1));
        $logoDireito = new Drawing();
        $caminhoLogoDireito = FCPATH . 'assets/images/Planifica.png';
        if (file_exists($caminhoLogoDireito)) {
            $logoDireito->setPath($caminhoLogoDireito)
                ->setCoordinates('F' . $currentRow)
                ->setHeight(30)
                ->setOffsetX(35 )
                ->setOffsetY(14)
                ->setWorksheet($sheet);
        }
        //Versão
        $textoVersao = "\n\n\nHorário " . $versionName;
        $sheet->setCellValue("F{$currentRow}", $textoVersao);
        $styleDireito = $sheet->getStyle("F{$currentRow}");
        $styleDireito->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_TOP);
        $styleDireito->getFont()->setBold(true)->setSize(8);
    }

    //Desenha o cabeçalho do grupo
    private function drawGroupHeader(Worksheet &$sheet, int &$currentRow, string $groupName, string $lastColumn): void
    {
        $sheet->mergeCells("A{$currentRow}:{$lastColumn}{$currentRow}");
        $sheet->setCellValue('A' . $currentRow, $groupName);
        $style = $sheet->getStyle('A' . $currentRow);
        $style->getFont()->setBold(true)->setColor(new Color(Color::COLOR_WHITE));
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1A5D1A');
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($currentRow)->setRowHeight(20);
    }

    //Desenha o cabeçalho dos dias
    private function drawDaysHeader(Worksheet &$sheet, int &$currentRow, array $diasDaSemana, array $colunasDias, string $lastColumn): void
    {
        $sheet->setCellValue('A' . $currentRow, 'Horário');
        foreach ($diasDaSemana as $numDia => $nomeDia) {
            $sheet->setCellValue($colunasDias[$numDia] . $currentRow, $nomeDia);
        }
        $style = $sheet->getStyle("A{$currentRow}:{$lastColumn}{$currentRow}");
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD1E7D1');
        $style->getFont()->setBold(true);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    //Desenha a grade
    private function drawTimetableGrid(Worksheet &$sheet, int &$currentRow, array $schedule, array $diasDaSemana, array $colunasDias, array $cellKeys): void
    {
        $horariosPorPeriodo = ['MANHÃ' => [], 'TARDE' => [], 'NOITE' => []];
        $todosOsHorarios = [];
        foreach ($schedule as $dia => $aulas) {
            $todosOsHorarios = array_merge($todosOsHorarios, array_keys($aulas));
        }
        $todosOsHorarios = array_unique($todosOsHorarios);
        sort($todosOsHorarios);

        foreach ($todosOsHorarios as $hora) {
            $h = (int)substr($hora, 0, 2);
            if ($h < 12) $horariosPorPeriodo['MANHÃ'][] = $hora;
            elseif ($h < 18) $horariosPorPeriodo['TARDE'][] = $hora;
            else $horariosPorPeriodo['NOITE'][] = $hora;
        }

        $startRowOfGrid = $currentRow;

        foreach ($horariosPorPeriodo as $periodo => $horas) {
            if (empty($horas)) {
                continue;
            }

            // Cabeçalho do período 
            $sheet->mergeCells("A{$currentRow}:F{$currentRow}");
            $sheet->setCellValue('A' . $currentRow, $periodo);
            $style = $sheet->getStyle("A{$currentRow}");
            $style->getFont()->setBold(true)->setColor(new Color(Color::COLOR_WHITE));
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1A5D1A');
            $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $currentRow++;

            foreach ($horas as $hora) {
                $sheet->setCellValue('A' . $currentRow, $hora);
                foreach ($diasDaSemana as $numDia => $nomeDia) {
                    $coluna = $colunasDias[$numDia];
                    if (isset($schedule[$numDia][$hora])) {
                        $aula = $schedule[$numDia][$hora];
                        $textoCelulaArray = [];
                        foreach ($cellKeys as $key) {
                            $textoCelulaArray[] = $aula[$key] ?? '';
                        }
                        $textoCelula = implode("\n", $textoCelulaArray);
                        $sheet->setCellValue($coluna . $currentRow, $textoCelula);
                    } else {
                        $sheet->setCellValue($coluna . $currentRow, '—');
                    }
                }
                $sheet->getRowDimension($currentRow)->setRowHeight(45);
                $currentRow++;
            }
        }

        // Bordas e alinhamento de toda a grade de horários.
        if ($currentRow > $startRowOfGrid) {
            $finalRow = $currentRow - 1;
            $rangeTabela = "A{$startRowOfGrid}:F{$finalRow}";
            $style = $sheet->getStyle($rangeTabela);
            $style->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $style->getBorders()->getAllBorders()->setBorderStyle('thin')->setColor(new Color('FFBFBFBF'));
        }
    }
}