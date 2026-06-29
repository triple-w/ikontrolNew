<?php

namespace App\Http\Controllers;

class AdministrationController extends Controller
{
    public function usuarios()
    {
        return $this->placeholder('usuarios');
    }

    public function roles()
    {
        return $this->placeholder('roles');
    }

    public function general()
    {
        return $this->placeholder('general');
    }

    private function placeholder(string $module)
    {
        $modules = [
            'usuarios' => [
                'title' => 'Usuarios',
                'description' => 'Modulo preparado para administrar accesos del equipo.',
                'objective' => 'Crear usuarios, controlar estados y asignar permisos cuando se defina el modelo de seguridad.',
            ],
            'roles' => [
                'title' => 'Roles y permisos',
                'description' => 'Base para separar permisos administrativos, comerciales, operativos y fiscales.',
                'objective' => 'Definir perfiles de acceso sin alterar el login actual ni el modulo fiscal.',
            ],
            'general' => [
                'title' => 'Configuracion general',
                'description' => 'Area prevista para preferencias globales de la plataforma iKontrol.',
                'objective' => 'Centralizar ajustes generales cuando existan modulos comerciales y operativos.',
            ],
        ];

        return view('platform.placeholder', [
            'area' => 'Configuracion',
            'title' => $modules[$module]['title'],
            'description' => $modules[$module]['description'],
            'objective' => $modules[$module]['objective'],
        ]);
    }
}
