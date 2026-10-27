// Genera docs/manuales/manual-usuario.docx
const fs = require('fs');
const path = require('path');
const C = require('./comun');
const { P, H1, H2, H3, vineta, numerado, tabla, nota } = C;

const SALIDA = process.argv[2];
const CAP = process.argv[3];
const tamanos = JSON.parse(fs.readFileSync(path.join(CAP, 'tamanos.json'), 'utf8'));
let figura = 0;
// Captura de teléfono (ancho fijo) o de computadora.
function fig(nombre, texto) {
  if (process.env.SIN_IMAGENES) { figura += 1; return [P(`[imagen ${nombre}] Figura ${figura}. ${texto}`)]; }
  const [w, h] = tamanos[nombre];
  const ancho = nombre.startsWith('t') ? 205 : 560;
  figura += 1;
  return C.imagen(path.join(CAP, nombre + '.png'), ancho, Math.round(ancho * h / w), `Figura ${figura}. ${texto}`);
}
// Captura de teléfono con su explicación al lado.
function figT(nombre, texto, ...textos) {
  figura += 1;
  if (process.env.SIN_IMAGENES) return [P(`[imagen ${nombre}] Figura ${figura}. ${texto}`), ...textos];
  const [w, h] = tamanos[nombre];
  const ancho = 158;
  return C.imagenConTexto(path.join(CAP, nombre + '.png'), ancho, Math.round(ancho * h / w), `Figura ${figura}. ${texto}`, textos);
}
let lista = 0;
const pasos = () => { lista += 1; return `pasos${Math.min(lista, 12)}`; };

const cuerpo = [
  ...tabla(['Versión', 'Fecha', 'Descripción'], [
    ['1.0', '16 de octubre de 2026', 'Versión inicial, para la prueba con el personal del Puesto de Salud de Chivarreto.'],
    ['1.1', '21 de octubre de 2026', 'Código de ingreso al correo y recuperación de contraseña; inicio en Niños; número de carnet; registrar varias vacunas a la vez y dosis cubiertas; corregir y anular registros; aviso de edad en suplementos; editar cuentas, enfermería de apoyo y catálogo de suplementos; ambiente de demostración.'],
    ['1.2', '27 de octubre de 2026', 'Instalar la aplicación en el teléfono (Android y iPhone) y aviso sin conexión; preguntas frecuentes sobre la aplicación.'],
  ], [1200, 2400, 5760], { titulo: 'Control de versiones del documento' }),
  ...C.indice(),

  // ------------------------------------------------------------------------
  H1('1. Introducción', true),
  P('El **Carnet Digital de Monitoreo Nutricional Infantil** es un sistema en internet que reemplaza el trabajo repetido en el carnet, el cuaderno SIGSA 5a y el formulario SIGSA 3. Se registra una vez la información de cada niño menor de 7 años y el sistema calcula solo su estado nutricional según los patrones de la Organización Mundial de la Salud (OMS).'),
  P('Lo más importante que hace el sistema es **avisar a tiempo**: además de los niños con desnutrición, muestra a los que están **cerca del umbral**, es decir, a punto de caer en desnutrición. En las gráficas de papel esos niños no se distinguen.'),
  P('Este manual explica, paso a paso y con imágenes, cómo usar el sistema desde el teléfono o la computadora.'),
  H2('1.1 Quién usa el sistema'),
  ...tabla(['Rol', 'Quiénes', 'Qué puede hacer'], [
    ['Enfermería', 'Enfermeras y enfermeros', 'Registrar niños, mediciones, vacunas, suplementos y citas; corregir o anular registros; ver el seguimiento, los historiales y los reportes. La enfermera de apoyo (sin sector) trabaja en todos los sectores.'],
    ['Consulta', 'Médicos, nutricionista, psicóloga', 'Ver toda la información y los reportes, sin modificar datos.'],
    ['Administrador', 'Encargado del sistema en el puesto', 'Crear, editar y dar de baja cuentas del personal, restablecer contraseñas y mantener el catálogo de suplementos. No registra datos de niños.'],
  ], [1700, 2600, 5060]),

  // ------------------------------------------------------------------------
  H1('2. Antes de empezar'),
  H2('2.1 Lo que se necesita'),
  vineta('Un teléfono Android o una computadora con internet.'),
  vineta('El navegador Chrome (en la computadora también sirven Edge o Firefox).'),
  vineta('Su usuario y contraseña, que le entrega el administrador del sistema.'),
  vineta('Un **correo electrónico propio** (por ejemplo, el Gmail de su teléfono), registrado en su cuenta: ahí le llega el código de ingreso y el enlace si olvida la contraseña.'),
  H2('2.2 La dirección del sistema'),
  P('Escriba en el navegador: **carnetchivarreto.com**'),
  P('**Para practicar:** en **pruebas.carnetchivarreto.com** está el ambiente de demostración, con niños inventados y una franja amarilla arriba. Ahí se puede probar todo sin tocar los datos reales. La cuenta de ese ambiente la da el administrador.'),
  H2('2.3 Instalar la aplicación en el teléfono'),
  P('El Carnet Digital se instala como una aplicación más del teléfono: queda con su ícono verde junto a las demás y se abre a pantalla completa, sin la barra del navegador. No se descarga de la tienda de aplicaciones ni ocupa espacio: es el mismo sistema, con los mismos datos.'),
  P('**En un teléfono Android (Chrome):**', { keepNext: true }),
  numerado('Abra **carnetchivarreto.com** en Chrome y espere unos segundos con la página abierta.', 'pasos11'),
  numerado('Toque el menú de los **tres puntos** (arriba a la derecha) y elija **Instalar aplicación**. En algunos teléfonos dice **Agregar a la pantalla principal** y después **Instalar**.', 'pasos11'),
  numerado('Confirme con **Instalar**. Aparece **Carnet Digital** entre sus aplicaciones.', 'pasos11'),
  P('**En un iPhone (Safari):**', { keepNext: true }),
  numerado('Abra **carnetchivarreto.com** en Safari.', 'pasos12'),
  numerado('Toque el botón **Compartir** (el cuadro con la flecha hacia arriba) y elija **Agregar a inicio**.', 'pasos12'),
  numerado('Toque **Agregar**.', 'pasos12'),
  ...nota('Si antes creó un **acceso directo** (un ícono con la letra C que se abre dentro de Chrome), bórrelo e instale la aplicación. El ambiente de práctica se instala igual desde pruebas.carnetchivarreto.com: su ícono es **naranja** y se llama **Carnet pruebas**, para no confundirlo con el real.'),
  P('La aplicación necesita internet, igual que la página. Sin conexión muestra el aviso **Sin conexión a internet** con el botón **Volver a intentar**. Por seguridad, no guarda en el teléfono ningún dato de los niños.'),
  H2('2.4 Cómo leer los resultados'),
  P('El sistema compara el peso y la talla del niño con millones de niños sanos medidos por la OMS. El resultado se llama **puntuación Z**: 0 es el niño promedio; los números negativos indican que está por debajo y los positivos, por encima. Usted no necesita calcular nada; el sistema lo traduce a colores y palabras:'),
  ...tabla(['Color', 'Nivel', 'Qué significa', 'Qué hacer'], [
    ['Rojo', 'Desnutrición aguda o crónica', 'El niño ya está por debajo del límite de la OMS (Z menor que -2).', 'Atención y seguimiento prioritarios.'],
    ['Naranja', 'Riesgo (bajo peso)', 'Bajo peso para su edad (Z menor que -2).', 'Vigilar en el próximo control.'],
    ['Amarillo', 'Cerca del umbral', 'Todavía no hay desnutrición, pero se acerca (Z entre -1 y -2).', 'Prevenir: consejería y control a tiempo.'],
    ['Azul', 'Sobrepeso', 'Peso alto para su talla (Z mayor que 2).', 'Orientación sobre alimentación.'],
    ['Verde', 'Normal', 'Dentro de lo esperado para su edad.', 'Continuar con sus controles.'],
  ], [1200, 2100, 3600, 2460]),
  P('Junto al nivel, el sistema indica si el niño **mejoró, empeoró o se mantuvo** respecto a su control anterior.'),

  // ------------------------------------------------------------------------
  H1('3. Ingresar al sistema'),
  ...figT('t01-ingreso', 'Pantalla de ingreso.',
    numerado('Abra **carnetchivarreto.com**.', pasos()),
    numerado('Escriba su **usuario** y su **contraseña**. Con el icono del ojo puede ver lo que escribió.', 'pasos1'),
    numerado('Toque **Ingresar**.', 'pasos1'),
    P('Si el usuario o la contraseña no son correctos, el sistema muestra "Usuario o contraseña incorrectos." Por seguridad no dice cuál de los dos falló.')
  ),
  H2('3.1 Código de ingreso en un teléfono nuevo'),
  P('La primera vez que entra desde un teléfono o una computadora, el sistema le envía un **código de 6 números a su correo**. Así, aunque alguien conozca su contraseña, no puede entrar desde otro teléfono.', { keepNext: true }),
  ...figT('t17-codigo', 'Código de ingreso.',
    numerado('Abra su correo: el código viene también en el asunto del mensaje, así se ve en el aviso del teléfono. Si no aparece, revise la carpeta de spam.', pasos()),
    numerado('Escriba los 6 números y toque **Confirmar**.', 'pasos2'),
    numerado('Deje marcada la casilla **No volver a pedirlo en este dispositivo por 90 días** si el teléfono es suyo. Desmárquela en un teléfono prestado.', 'pasos2'),
    P('El código vence en 10 minutos y admite 5 intentos. Si no le llega, toque **Enviar otro código** (un minuto después del anterior). Al administrador se le pide el código cada vez que entra.')
  ),
  ...nota('**Entrar con la huella:** cuando Chrome pregunte si quiere guardar la contraseña, toque **Guardar**. La próxima vez, Chrome la llena sola y, si así lo configura en el teléfono, le pide la huella antes.'),
  H2('3.2 Primer ingreso: cambiar la contraseña'),
  P('La primera vez, o cuando el administrador le restablezca la contraseña, usted entra con una **contraseña temporal** y el sistema le pide cambiarla antes de continuar.', { keepNext: true }),
  ...figT('t14-cambiar-pass', 'Cambio de contraseña.',
    numerado('Escriba su contraseña nueva en **Nueva contraseña**. Debe tener **al menos 8 caracteres, con letras y números**. La barra de colores le indica qué tan segura es.', pasos()),
    numerado('Escríbala otra vez en **Repetir contraseña**. Con el icono del **ojo** puede ver lo que escribió, y debajo el sistema avisa si **las contraseñas coinciden**.', 'pasos3'),
    numerado('Toque **Guardar y continuar**. Desde ese momento solo usted conoce su contraseña.', 'pasos3')
  ),
  H2('3.3 ¿Olvidó su contraseña?'),
  ...figT('t18-recuperar', 'Recuperar la contraseña.',
    numerado('En la pantalla de ingreso, toque **¿Olvidó su contraseña?**', pasos()),
    numerado('Escriba su **usuario** y toque **Enviar enlace**.', 'pasos4'),
    numerado('Abra el correo que le llega y toque el enlace. Sirve una sola vez y vence en 30 minutos.', 'pasos4'),
    numerado('Escriba su contraseña nueva dos veces y toque **Guardar contraseña**. Después, ingrese con ella.', 'pasos4'),
    P('Si su cuenta no tiene correo registrado, pida al administrador que le restablezca la contraseña.')
  ),
  H2('3.4 Salir y cierre automático'),
  vineta('Para salir, toque el botón de salida, arriba a la derecha.'),
  vineta('Por seguridad, si pasa **20 minutos sin usar el sistema**, la sesión se cierra sola y hay que volver a ingresar. Lo que ya guardó no se pierde.'),

  // ------------------------------------------------------------------------
  H1('4. El menú'),
  P('En el **teléfono**, el menú es una barra fija en la parte de abajo. En la **computadora**, está arriba. Tiene las mismas opciones, en el mismo orden:'),
  ...tabla(['Opción', 'Para qué sirve'], [
    ['Niños', 'Página de inicio de enfermería: buscar al niño que llegó, por nombre o número de carnet.'],
    ['Seguimiento', 'Los niños con alerta en su último control. Página de inicio de consulta.'],
    ['Registrar (Registrar niño en la computadora)', 'Registrar a un niño nuevo. Solo enfermería.'],
    ['Usuarios', 'Solo el administrador: cuentas del personal. Ocupa el lugar de Registrar.'],
    ['Citas', 'Calendario y lista de citas.'],
    ['Reportes', 'Panel de indicadores, exportaciones y formato SIGSA 3.'],
  ], [3300, 6060]),
  P('Arriba se muestra el nombre de la persona que ingresó y su rol.'),

  // ------------------------------------------------------------------------
  H1('5. Seguimiento: los niños en riesgo'),
  P('Muestra a cada niño **una sola vez**, según la condición más grave de su **último control**. Es la primera pantalla de quien tiene el rol de consulta.', { keepNext: true }),
  ...figT('t02-seguimiento', 'Panel de seguimiento en el teléfono.',
    vineta('**Tarjetas de colores:** cuántos niños hay en cada nivel. Las cifras son las mismas que en el Panel de indicadores.'),
    vineta('**Filtros:** elija un sector o un nivel y toque **Filtrar**.'),
    vineta('**Lista:** cada niño con su nivel, edad, sector, paraje, fecha del último control y el detalle de la alerta. Toque **Ver** para abrir su historial.'),
    vineta('**Imprimir lista:** hoja para las visitas domiciliarias, con el nombre de la madre, el teléfono y una columna en blanco para anotar.'),
    vineta('**Ver análisis en Indicadores:** abre el panel de reportes con el mismo sector.')
  ),
  ...fig('c01-seguimiento', 'Panel de seguimiento en la computadora.'),

  // ------------------------------------------------------------------------
  H1('6. Niños registrados'),
  P('Es la primera pantalla de la enfermería: sirve para encontrar al niño que llegó a consulta.', { keepNext: true }),
  ...figT('t03-ninos', 'Lista de niños.',
    vineta('**Buscar:** escriba parte del nombre o del apellido, o el **número de carnet** completo, y toque **Buscar**. El número de carnet aparece debajo del nombre.'),
    vineta('**Sector y estado:** filtre por sector y por niños activos o egresados.'),
    vineta('**Ordenar:** por nombre, edad, territorio y sector, paraje, estado o fecha de registro.'),
    vineta('**Ver** abre el historial del niño; **Editar** corrige sus datos personales (solo enfermería).')
  ),
  ...nota('Los niños que cumplen **7 años** pasan solos a estado **egresado**. Sus datos no se borran nunca: se pueden consultar eligiendo "Egresados" en el filtro.'),

  // ------------------------------------------------------------------------
  H1('7. Registrar un niño'),
  P('Solo el personal de enfermería registra niños. Toque **Registrar** en el menú.', { keepNext: true }),
  ...figT('t04-registrar-nino', 'Formulario para registrar a un niño (primera parte).',
    P('Los campos con asterisco (*) son obligatorios. El sistema escribe los nombres con mayúscula inicial y no deja escribir números en los nombres ni letras en el teléfono. Si algo falta o está mal, lo indica debajo del campo.'),
    P('La **fecha de nacimiento** se escribe con el teclado numérico, como día/mes/año: al escribir 25022024 aparece 25/02/2024. Debajo se muestra la edad que resulta, para revisar que la fecha esté bien.')
  ),
  H2('7.1 Datos que se piden'),
  ...tabla(['Sección', 'Campos', 'Indicaciones'], [
    ['Identificación', 'Nombres*, apellidos*, fecha de nacimiento*, sexo*, CUI, número de carnet, pueblo y comunidad lingüística', 'La fecha de nacimiento va como día/mes/año. Solo se registran niños menores de 7 años. El CUI es opcional: hay niños que aún no están inscritos en el RENAP. El número de carnet es el del carnet del puesto (letras, números, guion o barra); no se repite y sale como historia clínica en el SIGSA 3.'],
    ['Ubicación', 'Sector*, paraje*, dirección', 'Al elegir el sector aparecen sus parajes.'],
    ['Familia', 'Nombre de la madre o responsable, nombre del padre, teléfono', 'Escriba al menos uno de los dos nombres. El teléfono (8 dígitos) permite avisar las citas por WhatsApp.'],
  ], [1700, 3700, 3960]),
  H2('7.2 Niños de otro sector'),
  P('Cada enfermera registra sin avisos a los niños de **su sector**. Si el niño es de otro sector, el sistema muestra una **advertencia** y permite continuar; la operación queda anotada en la bitácora.'),
  H2('7.3 Después de registrar'),
  ...figT('t05-nino-registrado', 'Niño registrado: ¿qué desea hacer ahora?',
    P('Al guardar, aparece la confirmación con las acciones más comunes para seguir con el mismo niño:')
  ),

  // ------------------------------------------------------------------------
  H1('8. Registrar una medición'),
  P('Desde el historial del niño, toque **Nueva medición**, o **Registrar medición** después de registrarlo.', { keepNext: true }),
  ...figT('t06-medicion', 'Registro de una medición.',
    numerado('**Fecha de la toma:** el día en que se pesó y midió al niño. Viene con la fecha de hoy.', pasos()),
    numerado('**Peso** en kilogramos, con punto decimal (por ejemplo 11.40).', 'pasos5'),
    numerado('**Talla o longitud** en centímetros (por ejemplo 88.5).', 'pasos5'),
    numerado('**Tipo de medición:** el sistema sugiere **Longitud: acostado (infantómetro)** para menores de 2 años y **Talla: de pie (tallímetro)** desde los 2 años. Si lo midió de la otra forma, cámbielo: el sistema hace el ajuste que indica la OMS.', 'pasos5'),
    numerado('**Observaciones** (opcional) y toque **Guardar medición**.', 'pasos5')
  ),
  ...nota('**Valores imposibles:** si el peso o la talla no son posibles para la edad del niño (por ejemplo, se escribió 47 cm en lugar de 74), el sistema lo avisa y pide confirmar. Revise el dato antes de guardar.', 'aviso'),
  P('Al guardar, el sistema calcula en ese momento los indicadores, compara con el control anterior y, si corresponde, genera la alerta. El resultado se ve en el historial.'),

  // ------------------------------------------------------------------------
  H1('9. Historial del niño'),
  P('Reúne todo lo del niño en una sola pantalla.', { keepNext: true }),
  ...figT('t07-historial', 'Historial del niño (parte superior).',
    vineta('**Datos del niño:** edad, fecha de nacimiento, territorio, sector, paraje y estado.'),
    vineta('**Alerta del último control:** nivel, indicadores que la causaron con su puntuación Z y si mejoró o empeoró.'),
    vineta('**Botones:** Volver al listado, Ver gráficas de crecimiento y Carnet para la familia.'),
    vineta('**Mediciones registradas:** el botón **Nueva medición** y cada control con su fecha, edad, peso, talla y la puntuación Z de cada indicador con su color: P/E (peso para la edad), T/E (talla para la edad) y P/T o P/L (peso para la talla o la longitud).'),
    vineta('**Vacunación, suplementación y citas:** lo registrado y los botones para agregar.'),
    vineta('**Corregir y Anular:** junto a cada registro (ver el capítulo 14).')
  ),

  // ------------------------------------------------------------------------
  H1('10. Gráficas de crecimiento'),
  P('Desde el historial, toque **Ver gráficas de crecimiento**.', { keepNext: true }),
  ...figT('t08-graficas', 'Gráficas de crecimiento.',
    vineta('Las líneas son la referencia de la OMS: **-2 DE** (límite inferior, línea punteada), **mediana** (línea continua) y **+2 DE** (límite superior).'),
    vineta('Los puntos son las mediciones del niño. Toque uno para ver la fecha del control.'),
    vineta('Si el recorrido se acerca o cruza la línea inferior, hay riesgo nutricional.'),
    vineta('**Edad del niño** muestra el tramo de su edad; **Curva completa**, de 0 a 5 años. **Ampliar** abre la gráfica en pantalla completa.')
  ),
  ...nota('Las tablas de la OMS cargadas llegan a los 5 años. De 5 a 7 años el puesto solo lleva control de vacunación.'),

  // ------------------------------------------------------------------------
  H1('11. Vacunas'),
  P('Muchas vacunas se ponen en varias **dosis**: 1a, 2a y 3a forman la serie inicial; R1 y R2 son los **refuerzos**; **Única** se pone una vez. Como en una visita casi siempre se ponen dos o tres vacunas, se registran juntas. En el historial, toque **Registrar vacunas**.', { keepNext: true }),
  ...figT('t09-vacuna', 'Registro de varias vacunas a la vez.',
    numerado('Escriba la **fecha de aplicación** y elija el **lugar de vacunación** (establecimiento de salud, establecimiento educativo, miniconcentración, brigada móvil o casa: el código LV del SIGSA). Valen para todas las dosis que marque.', pasos()),
    numerado('En **Dosis pendientes para su edad**, marque las que se aplicaron. Al marcar una aparece su campo **Lote**: escríbalo.', 'pasos6'),
    numerado('Toque **Guardar**. El sistema no deja registrar dos veces la misma dosis.', 'pasos6'),
    P('Abajo, plegados, están **Otras dosis del esquema vigente** (las que todavía no le tocan) y **Esquema anterior (carnet de 2018)**, para pasar al sistema las dosis que ya trae el carnet de papel.')
  ),
  H2('11.1 El esquema del niño, en colores'),
  ...figT('t21-esquema', 'Esquema de vacunación del niño.',
    P('En el historial, toque **Ver esquema de vacunación completo**. Cada dosis del esquema nacional 2026 aparece con un color:'),
    vineta('**Aplicada** (verde azulado): ya está registrada.'),
    vineta('**Pendiente** (naranja): por su edad ya le tocaba y no está registrada. Revise el carnet o póngasela.'),
    vineta('**Próxima** (gris): todavía no le toca.'),
    vineta('**Cubierta** (azul): se la cubre una dosis del carnet de 2018. No se repite; revise el carnet.')
  ),
  H2('11.2 Dosis cubiertas: el cambio de esquema'),
  P('Antes, con el carnet de 2018, a los 2, 4 y 6 meses se ponía la **Pentavalente** y aparte la vacuna de la polio. Ahora se pone la **Hexavalente**, que es la Pentavalente más la polio en una sola inyección. A un niño que recibió la Pentavalente 1a y 2a, el sistema le marca la Hexavalente 1a y 2a como **cubiertas** (no se repiten) y la Hexavalente 3a como **pendiente** si ya le toca. Las dosis que se cubren así son:'),
  ...tabla(['Carnet de 2018 (antes)', 'Cubre en el esquema 2026'], [
    ['Pentavalente 1a, 2a y 3a', 'Hexavalente 1a, 2a y 3a'],
    ['Polio (fIPV) 2a', 'OPV 2a'],
    ['DPT Refuerzo 1', 'Hexavalente Refuerzo 1'],
  ], [4680, 4680]),

  // ------------------------------------------------------------------------
  H1('12. Suplementos'),
  ...figT('t10-suplemento', 'Registro de la entrega de un suplemento.',
    P('En el historial, toque **Registrar suplemento**. Elija el suplemento o desparasitante, la fecha de entrega y la cantidad, y toque **Guardar**.'),
    P('Debajo del suplemento aparecen su presentación, su indicación y la edad para la que es (por ejemplo, el suplemento nutricional lipídico: un sobre de 20 g al día, de 6 a 23 meses). Si el niño está fuera de esa edad, el sistema avisa, pero deja guardar.'),
    P('La lista de suplementos la mantiene el administrador (capítulo 17).')
  ),

  // ------------------------------------------------------------------------
  H1('13. Citas'),
  H2('13.1 Programar una cita'),
  ...figT('t11-cita', 'Programar una cita.',
    P('En el historial, toque **Programar cita**. Elija la fecha, el motivo y, si quiere, una indicación para la madre. Toque **Guardar**.'),
    P('Si el niño no tiene teléfono registrado, el sistema avisa que no podrá enviar el recordatorio por WhatsApp; puede agregarlo editando los datos del niño.')
  ),
  H2('13.2 Calendario y lista'),
  ...figT('t12-citas', 'Calendario de citas.',
    P('En el menú, toque **Citas**.'),
    vineta('El calendario marca cuántas citas hay cada día. El color del día avisa la carga: **hasta 8 citas** normal, **de 9 a 15** alta afluencia y **más de 15**, excedido.'),
    vineta('Toque un día para ver sus citas.'),
    vineta('En **Lista** aparecen las próximas citas, con el botón **Avisar por WhatsApp**: abre WhatsApp con el mensaje para la madre ya escrito. Usted solo toca enviar. Es gratuito.'),
    vineta('Cuando llegue el día, marque la cita como **Asistió** o **No asistió**.')
  ),

  // ------------------------------------------------------------------------
  H1('14. Corregir o anular un registro'),
  P('Como en el carnet de papel, **nada se borra**: lo equivocado se anula y queda **tachado**, como con la línea roja, con quién lo anuló, cuándo y por qué. Lo anulado deja de contar en las alertas, el seguimiento, las gráficas, los reportes, el SIGSA 3 y el carnet para la familia.', { keepNext: true }),
  ...figT('t19-anular', 'Anular una medición de un mes anterior.',
    numerado('En el historial, junto al registro, toque **Anular** (cualquier registro, también una medición) o **Corregir** (vacuna, suplemento o cita: fecha, lote, lugar, cantidad u observaciones).', pasos()),
    numerado('Escriba el **motivo**, por ejemplo "Se anotó el peso de otro niño".', 'pasos7'),
    numerado('**Regla del mes, como en SIGSA Web:** si la atención es de este mes, basta el motivo. Si es de un mes anterior, el sistema pide además la **referencia del conocimiento**.', 'pasos7'),
    numerado('Toque **Anular** o **Guardar corrección**. Si anuló una medición, puede registrar la correcta.', 'pasos7')
  ),
  ...fig('t20-anulado', 'Así queda una medición anulada en el historial.'),
  P('Para cambiar la vacuna, la dosis o la clase de suplemento, se anula el registro y se registra el correcto. Toda corrección queda en la bitácora con el valor anterior y el nuevo.'),

  H1('15. Carnet para la familia'),
  P('Es una copia del carnet para la madre, la escuela o el hogar comunitario. Desde el historial, toque **Carnet para la familia**.'),
  ...fig('c03-carnet-familia', 'Carnet para la familia (vista en la computadora).'),
  vineta('Trae los datos del niño, las vacunas con fecha y las pendientes, los controles de crecimiento con su resultado en palabras sencillas y gráficas, los suplementos, la fecha de emisión, y espacio para firma y sello.'),
  vineta('Toque **Imprimir o guardar como PDF**. En el teléfono, elija "Guardar como PDF" y luego compártalo por WhatsApp.'),
  vineta('Cada copia queda anotada en la bitácora.'),

  // ------------------------------------------------------------------------
  H1('16. Reportes e indicadores'),
  ...figT('t13-reportes', 'Panel de indicadores en el teléfono.',
    P('En el menú, toque **Reportes**. Es el análisis del puesto para tomar decisiones: cuántos niños, dónde están y si la situación mejora.')
  ),
  H2('16.1 Filtros'),
  P('Período (abre en **Estado actual**: el último control de cada niño), fechas, territorio, sector, paraje, sexo y grupo de edad. Toque **Aplicar**; **Quitar filtros** vuelve al inicio.'),
  H2('16.2 Tarjetas y gráficas'),
  vineta('**Tarjetas:** niños activos, porcentaje con control registrado, controles atrasados, inasistencia a citas y niños por nivel.'),
  vineta('**Estado nutricional por sector** y **Parajes con más casos:** toque una barra para filtrar el panel por ese sector o paraje.'),
  vineta('**Condición más grave** y **Cada condición por separado:** la segunda cuenta a un niño en todas sus condiciones (por ejemplo, desnutrición crónica y sobrepeso a la vez).'),
  vineta('**Tendencia de 12 meses.**'),
  vineta('Toque una cifra para ver la lista de esos niños.'),
  ...fig('c02-reportes', 'Panel de indicadores en la computadora.'),
  H2('16.3 Exportar'),
  ...tabla(['Botón', 'Resultado'], [
    ['PDF', 'Abre una versión para imprimir o guardar como PDF.'],
    ['Excel', 'Descarga un libro de Excel con 6 hojas.'],
    ['CSV', 'Descarga los datos para otros programas.'],
  ], [1800, 7560]),
  P('Las tres exportaciones tienen las mismas cifras que el panel y quedan anotadas en la bitácora.'),
  H2('16.4 Formato SIGSA 3 del día'),
  P('Toque **SIGSA 3 del día** para obtener la hoja oficial del registro diario de consulta con las atenciones a niños ya llenas: el número de carnet como historia clínica y, en la vacunación, "Vacunación" en el diagnóstico y la vacuna con su dosis en la columna del código. Elija el día o el mes completo y el papel (oficio o carta), y toque **Imprimir o guardar como PDF**. Se escribe una vez en el sistema y se imprime donde haga falta.'),
  ...fig('c04-sigsa3', 'Formato SIGSA 3 listo para imprimir.'),

  // ------------------------------------------------------------------------
  H1('17. Administración'),
  P('Solo para el administrador, que entra directamente a **Gestión de usuarios**. Al administrador el sistema le pide el código del correo cada vez que ingresa.'),
  H2('17.1 Crear una cuenta'),
  ...figT('t16-usuario-nuevo', 'Crear un usuario nuevo.',
    numerado('Toque **Nuevo usuario**.', pasos()),
    numerado('Escriba el **nombre completo**, el **correo electrónico** (puede ser el personal de la persona) y el **nombre de usuario** (letras sin tilde, números, punto o guion).', 'pasos8'),
    numerado('Elija el **rol**. Para enfermería, elija su **sector**; sin sector es una **enfermera de apoyo**, que trabaja en todos los sectores.', 'pasos8'),
    numerado('Toque **Crear usuario**.', 'pasos8')
  ),
  ...figT('t15-usuarios', 'Datos de ingreso de la cuenta nueva, listos para copiar o enviar.',
    numerado('El sistema muestra el **usuario** y una **contraseña temporal**, que la persona deberá cambiar en su primer ingreso.', 'pasos8'),
    numerado('Entregue los datos de preferencia en ese momento, o con uno de los dos botones: **Copiar datos de ingreso** (para pegarlos en un mensaje o un documento) o **Enviar por WhatsApp** (abre WhatsApp con el mensaje listo; elija a la persona). Envíelos solo a la persona dueña de la cuenta.', 'pasos8')
  ),
  H2('17.2 Editar una cuenta'),
  P('Toque **Editar** en la tarjeta de la persona para corregir su nombre (por ejemplo, uno cambiado por el autocorrector), su usuario, su correo, su rol o su sector. La contraseña no cambia y la corrección queda en la bitácora.'),
  H2('17.3 Contraseña olvidada'),
  P('Si la persona tiene correo, puede recuperarla sola con **¿Olvidó su contraseña?** Si no, toque **Restablecer contraseña** en su tarjeta y confirme: aparecen su usuario y una nueva contraseña temporal, con los mismos botones para copiar o enviar por WhatsApp. Al restablecerla, el sistema le vuelve a pedir el código en todos sus teléfonos (útil si perdió el teléfono).'),
  H2('17.4 Dar de baja'),
  P('**Dar de baja** impide que la persona ingrese, sin borrar lo que registró. Se puede reactivar después.'),
  H2('17.5 Catálogo de suplementos'),
  ...figT('t22-catalogo-suplementos', 'Catálogo de suplementos.',
    P('En Gestión de usuarios, toque **Catálogo de suplementos**. Ahí se agrega un suplemento nuevo (nombre, presentación, indicación y, si se quiere, la edad en meses) y se **desactiva** el que ya no se entrega: deja de salir al registrar y las entregas anteriores se conservan. Si ya tiene entregas, su nombre no se puede cambiar.')
  ),

  // ------------------------------------------------------------------------
  H1('18. Preguntas frecuentes'),
  ...tabla(['Situación', 'Qué hacer'], [
    ['El sistema me sacó y pide ingresar otra vez', 'Pasaron 20 minutos sin uso. Vuelva a ingresar; lo guardado no se pierde.'],
    ['Olvidé mi contraseña', 'Toque "¿Olvidó su contraseña?" en la pantalla de ingreso. Si su cuenta no tiene correo, pida al administrador que la restablezca.'],
    ['No me llega el código', 'Revise la carpeta de spam y espere un minuto. Toque "Enviar otro código". Si sigue sin llegar, pida al administrador que revise el correo de su cuenta.'],
    ['¿Por qué me pide el código otra vez?', 'Entró desde otro teléfono o computadora, pasaron 90 días, le restablecieron la contraseña o es administrador.'],
    ['No encuentro a un niño', 'Revise los filtros de sector y estado: puede estar como egresado si ya cumplió 7 años.'],
    ['No aparecen las gráficas', 'Las gráficas necesitan internet. Revise la conexión y recargue la página.'],
    ['No me aparece "Instalar aplicación"', 'Espere unos segundos con la página abierta en Chrome y recárguela. En iPhone, use Safari: Compartir y Agregar a inicio (sección 2.3).'],
    ['La aplicación dice "Sin conexión a internet"', 'Revise los datos móviles o el wifi y toque Volver a intentar.'],
    ['El sistema dice que un valor parece imposible', 'Revise el peso, la talla y la fecha de nacimiento. Si el dato es correcto, confirme.'],
    ['Me equivoqué en una medición, vacuna o cita', 'Use Corregir o Anular en el historial del niño, con el motivo (capítulo 14).'],
    ['Una vacuna aparece como "cubierta"', 'El niño recibió la dosis equivalente del carnet de 2018. No se repite; revise el carnet.'],
    ['El botón de WhatsApp no aparece', 'El niño no tiene teléfono registrado. Agréguelo con Editar en la lista de niños.'],
    ['Registré a un niño en otro sector', 'Es permitido: queda la advertencia en la bitácora.'],
  ], [3500, 5860]),

  H1('19. Glosario'),
  ...tabla(['Término', 'Significado'], [
    ['Puntuación Z', 'Cuántas desviaciones estándar está el niño por encima o por debajo del promedio de la OMS para su edad y sexo.'],
    ['DE', 'Desviación estándar.'],
    ['P/E', 'Peso para la edad.'],
    ['T/E', 'Talla (o longitud) para la edad. Si es baja, indica desnutrición crónica.'],
    ['P/T y P/L', 'Peso para la talla (desde los 2 años) y peso para la longitud (menores de 2 años). Si es bajo, indica desnutrición aguda.'],
    ['Cerca del umbral', 'Puntuación Z entre -1 y -2: el niño se acerca a la desnutrición sin haberla alcanzado.'],
    ['Longitud y talla', 'Longitud: medida acostado, en menores de 2 años. Talla: medida de pie, desde los 2 años.'],
    ['CUI', 'Código Único de Identificación del RENAP.'],
    ['SIGSA', 'Sistema de Información Gerencial de Salud del Ministerio de Salud.'],
    ['Sector y paraje', 'División del territorio del puesto y comunidad donde vive el niño.'],
    ['Bitácora', 'Registro automático de quién hizo cada operación y cuándo.'],
    ['Dosis y refuerzo', 'Cada aplicación de una vacuna. 1a, 2a y 3a forman la serie inicial; R1 y R2 son los refuerzos.'],
    ['Dosis cubierta', 'Dosis del esquema 2026 que no se repite porque el niño recibió la equivalente del carnet de 2018.'],
    ['Conocimiento', 'Documento con el que se justifica corregir una atención de un mes anterior, como en el cuaderno SIGSA 5a.'],
    ['Código de ingreso', 'Número de 6 cifras que llega al correo al entrar desde un teléfono nuevo.'],
  ], [2300, 7060]),
];

const doc = C.documento({
  titulo: 'Manual de usuario, Carnet Digital de Monitoreo Nutricional Infantil',
  nombreCorto: 'Manual de usuario',
  portadaHijos: C.portada('MANUAL DE USUARIO', '1.2', 'octubre de 2026'),
  cuerpo,
});
C.guardar(doc, SALIDA);
