<?php
$sel = fn(string $campo, string $valor): string => ($filtros[$campo] ?? '') === $valor ? 'selected' : '';
$chk = fn(string $valor): string => ($filtros['especie'] ?? '') === $valor ? 'checked' : '';
?>
<form class="buscador-form" action="mascotas.php" method="get">
    <div class="chips" role="radiogroup" aria-label="Especie">
        <?php if (!empty($conTodos)): ?>
            <label class="chip"><input type="radio" name="especie" value="" <?= $chk('') ?>><span>Todos</span></label>
        <?php endif; ?>
        <label class="chip"><input type="radio" name="especie" value="perro" <?= $chk('perro') ?>><span>Perro</span></label>
        <label class="chip"><input type="radio" name="especie" value="gato" <?= $chk('gato') ?>><span>Gato</span></label>
    </div>

    <select name="edad" aria-label="Edad">
        <option value="">Edad</option>
        <option value="cachorro" <?= $sel('edad', 'cachorro') ?>>Cachorro (menos de 1 año)</option>
        <option value="joven"    <?= $sel('edad', 'joven') ?>>Joven (1 a 2 años)</option>
        <option value="adulto"   <?= $sel('edad', 'adulto') ?>>Adulto (3 años o más)</option>
    </select>

    <select name="tamano" aria-label="Tamaño">
        <option value="">Tamaño</option>
        <option value="chico"   <?= $sel('tamano', 'chico') ?>>Chico</option>
        <option value="mediano" <?= $sel('tamano', 'mediano') ?>>Mediano</option>
        <option value="grande"  <?= $sel('tamano', 'grande') ?>>Grande</option>
    </select>

    <button type="submit" class="btn buscador-btn">Buscar</button>
</form>
