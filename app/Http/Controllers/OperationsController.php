<?php

namespace App\Http\Controllers;

class OperationsController extends Controller
{
    public function actividades()
    {
        return $this->placeholder('actividades');
    }

    public function calendario()
    {
        return $this->placeholder('calendario');
    }

    public function tareas()
    {
        return $this->placeholder('tareas');
    }

    public function proyectos()
    {
        return $this->placeholder('proyectos');
    }

    private function placeholder(string $module)
    {
        $modules = [
            'actividades' => [
                'title' => 'Actividades',
                'description' => 'Registro futuro de interacciones, llamadas, visitas y seguimientos comerciales.',
                'objective' => 'Dar trazabilidad operativa al trabajo diario del equipo.',
            ],
            'calendario' => [
                'title' => 'Calendario',
                'description' => 'Agenda preparada para compromisos, recordatorios y eventos de operacion.',
                'objective' => 'Visualizar actividades por dia, semana y mes cuando exista la logica operativa.',
            ],
            'tareas' => [
                'title' => 'Tareas',
                'description' => 'Base visual para un flujo de pendientes y tablero Kanban futuro.',
                'objective' => 'Organizar trabajo por responsables, prioridad y estado.',
            ],
            'proyectos' => [
                'title' => 'Proyectos',
                'description' => 'Modulo previsto para agrupar tareas, actividades y entregables por iniciativa.',
                'objective' => 'Coordinar trabajo comercial y operativo sin mezclarlo con documentos fiscales.',
            ],
        ];

        return view('platform.placeholder', [
            'area' => 'Operacion',
            'title' => $modules[$module]['title'],
            'description' => $modules[$module]['description'],
            'objective' => $modules[$module]['objective'],
        ]);
    }
}
