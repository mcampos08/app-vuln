<?php
use PHPUnit\Framework\TestCase;

class LoginTest extends TestCase
{
    public function testLoginFallidoMuestraMensajeError()
    {
        // Simular POST incorrecto
        $_POST['username'] = 'usuario_invalido';
        $_POST['password'] = 'clave_invalida';

        // Capturar salida
        ob_start();
        include __DIR__ . '/../public/index.php';
        $salida = ob_get_clean();

        $this->assertStringContainsString('Credenciales inválidas', $salida);
    }
}
