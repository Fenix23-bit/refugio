En la terminal que tienes que localizar la carpeta donde este el proyecto (C:\xampp\htdocs\refugio>), ejecuta el servidor integrado de PHP indicando que la raíz es -t public:

C:\xampp\php\php.exe -S localhost:3000 -t public

Luego abre tu navegador en http://localhost:3000.


## Base de datos y datos de prueba

1. Enciende **Apache** y **MySQL** desde el panel de XAMPP.

2. Crea la base de datos y las tablas importando `sql/esquema.sql`.
   **Cuidado:** este archivo borra y vuelve a crear las tablas, así que elimina los datos que ya existan.

   - Con phpMyAdmin: entra a `http://localhost/phpmyadmin`, ve a la pestaña **Importar** y elige `sql/esquema.sql`.
   - O por terminal, desde la carpeta del proyecto:

   ```
   C:\xampp\mysql\bin\mysql.exe -u root < sql\esquema.sql
   ```

3. Carga los datos de prueba (6 mascotas con sus fotos y 2 usuarios). Desde la carpeta del proyecto (`C:\xampp\htdocs\refugio>`):

   ```
   C:\xampp\php\php.exe sql\seed.php
   ```

   El script se puede ejecutar más de una vez: lo que ya existe (misma mascota o mismo correo) se omite y no se duplica.

### Qué se crea

| Mascota | Especie | Estado |
|---|---|---|
| Luna | Perro | disponible |
| Mila | Gato | disponible |
| Nina | Gato | disponible |
| Rocky | Perro | disponible |
| Toby | Perro | disponible |
| Toby (adoptado) | Perro | adoptado (no se muestra en el sitio público) |

Las fotos se toman de `public/img/mascotas/` (`prueba.jpg`, `gato.jpg`, `nina.jpg`, `rocky.jpg`, `toby.jpg` y `toby-adoptado.jpg`). Si falta alguna, el script lo avisa.

### Usuarios de prueba

| Correo | Contraseña | Rol |
|---|---|---|
| `admin@huellitas.test` | `prueba123` | administrador (entra a `/screens/admin.php`) |
| `alex@huellitas.test` | `prueba123` | usuario |

Las contraseñas se guardan hasheadas con `password_hash()`.
