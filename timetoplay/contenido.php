<?php
// Notas privadas del DM. Solo se muestran a través de index.php (admin):
// un .php nunca se entrega como texto, así que Nginx no puede saltarse el permiso.
if (!defined('FAVOR_TIMETOPLAY')) { http_response_code(404); exit; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Wiki de Waterdeep — Favor de Jack</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-mark">W</div>
        <div>
          <strong>Waterdeep Wiki</strong>
          <span>Favor de Jack</span>
        </div>
      </div>

      <label class="search">
        <span>⌕</span>
        <input id="searchInput" type="search" placeholder="Buscar en la wiki..." />
      </label>

      <nav id="wikiNav">
        <a href="#inicio" class="active">Inicio</a>
        <div class="nav-title">Waterdeep</div>
        <a href="#waterdeep">Situación de Waterdeep</a>
        <a href="#gobierno">Cambio de gobierno</a>
        <a href="#gremios">Problema de los gremios</a>

        <div class="nav-title">Facciones</div>
        <a href="#per-favore">Per-Favore</a>
        <a href="#guantelete">Orden del Guantelete</a>
        <a href="#eclipse">Guardia del Eclipse</a>
        <a href="#lores">Alianza de los Lores</a>
        <a href="#candado">Candado Sangriento</a>

        <div class="nav-title">Región</div>
        <a href="#neverwinter">Neverwinter</a>
        <a href="#baldurs">Baldur's Gate</a>

        <div class="nav-title">Conflicto actual</div>
        <a href="#seraphim">Seraphim Oscuro</a>
        <a href="#supervision">Supervisión</a>
        <a href="#recompensas">Objetos y recompensas</a>
        <a href="#plan-dickson">Plan de Dickson</a>
        <a href="#taxmen">Tax-Men</a>
        <a href="#arco">Estructura del arco</a>
      </nav>
    </aside>

    <main class="content">
      <header class="hero" id="inicio">
        <div class="eyebrow">Archivo de campaña · D&D · Waterdeep</div>
        <h1>Waterdeep y la guerra silenciosa de los gremios</h1>
        <p class="lead">
          Tras años de atentados, demonios, no muertos y catástrofes mágicas, Waterdeep cambia de rumbo.
          Richard Dickson quiere recuperar el control de la ciudad, limitar el poder de los gremios y reemplazar
          progresivamente a los aventureros con una fuerza obediente al gobierno: los Tax-Men.
        </p>

        <div class="summary-grid">
          <article class="stat">
            <span class="stat-label">Campaña</span>
            <strong>≈ 3 años</strong>
            <small>Mismo mundo y continuidad narrativa.</small>
          </article>
          <article class="stat">
            <span class="stat-label">Nivel actual</span>
            <strong>≈ Nivel 7</strong>
            <small>Personajes actuales del grupo.</small>
          </article>
          <article class="stat">
            <span class="stat-label">Per-Favore</span>
            <strong>≈ 32 personajes</strong>
            <small>Fuerza considerable para un gremio.</small>
          </article>
          <article class="stat">
            <span class="stat-label">Próximas misiones</span>
            <strong>1–4</strong>
            <small>Etapa de supervisión y escalada.</small>
          </article>
        </div>
      </header>

      <section class="wiki-section" id="waterdeep" data-search="waterdeep atentados terrorismo demonios no muertos magia capucha roja">
        <div class="section-kicker">Ciudad</div>
        <h2>Waterdeep</h2>
        <p>
          Waterdeep lleva años atravesando amenazas que superan con frecuencia la capacidad de su guardia tradicional.
          La ciudad ha sufrido ataques terroristas, apariciones de no muertos, demonios, criaturas extremadamente poderosas,
          incidentes mágicos y la presencia de gremios de aventureros que actúan casi como fuerzas independientes.
        </p>
        <div class="callout danger">
          <strong>Antecedente clave:</strong>
          hace algunos meses fueron asesinados nobles y mercaderes importantes durante un atentado.
        </div>
        <h3>Incidente de Capucha Roja</h3>
        <p>
          Uno de los ataques anteriores estuvo relacionado con Capucha Roja, un terrorista que utilizó una toxina desde una
          de las torres más altas de Waterdeep. El grupo terminó enfrentándolo. Allon descubrió su invisibilidad mediante
          llamas azules y Capucha Roja terminó herido y cayendo desde la torre.
        </p>
        <p>
          El resultado político de estos incidentes es claro: el gobierno considera que el sistema tradicional de protección
          ya no funciona correctamente.
        </p>
      </section>

      <section class="wiki-section" id="gobierno" data-search="richard dickson jonathan gobernador militar autoritario seguridad aventureros">
        <div class="section-kicker">Política</div>
        <h2>Cambio de gobierno</h2>
        <div class="profile-card">
          <div class="profile-avatar">RD</div>
          <div>
            <h3>Richard Dickson</h3>
            <p>Nuevo gobernador de Waterdeep. Sustituye a Jonathan.</p>
            <div class="tags">
              <span>Militar</span><span>Pragmático</span><span>Seguridad</span><span>Desconfía de aventureros</span>
            </div>
          </div>
        </div>
        <p>
          Dickson parte de una idea sencilla: Waterdeep no puede seguir dependiendo de grupos de aventureros independientes
          cada vez que aparece una amenaza importante, especialmente cuando esos mismos grupos pueden destruir propiedades,
          provocar daños colaterales, acumular cantidades absurdas de poder y actuar sin responder directamente ante el gobierno.
        </p>
        <blockquote>
          “Durante años Waterdeep ha sobrevivido gracias a aventureros. Pero también durante años Waterdeep ha estado a punto
          de desaparecer por culpa de aventureros.”
        </blockquote>
      </section>

      <section class="wiki-section" id="gremios" data-search="gremios poder milicia arrestar investigar tarifa 200 oro aventurero influencia">
        <div class="section-kicker">Sistema</div>
        <h2>El problema de los gremios</h2>
        <p>
          Los gremios de aventureros ya no son simples agrupaciones de mercenarios. Se han convertido en instituciones con
          dinero, reputación, combatientes poderosos, recursos e influencia política.
        </p>
        <ul class="clean-list">
          <li>Pueden investigar e intervenir en problemas de seguridad.</li>
          <li>Algunos tienen autoridad comparable a la milicia.</li>
          <li>Pueden llegar a arrestar personas.</li>
          <li>Existe una tarifa mínima aproximada de 200 po por aventurero para determinados encargos.</li>
          <li>Para Dickson, permitir que sigan creciendo sin control es un riesgo a largo plazo.</li>
        </ul>
      </section>

      <section class="wiki-section faction" id="per-favore" data-search="per favore favor de jack gremio 32 personajes dios caos investigación">
        <div class="section-kicker">Facción</div>
        <h2>Per-Favore / Favor de Jack</h2>
        <div class="two-col">
          <div>
            <p>
              El gremio principal relacionado con los jugadores. Su sede está en Waterdeep y alrededor de él han girado gran
              parte de las aventuras de los últimos años.
            </p>
            <p>
              La cifra aproximada manejada actualmente es de <strong>32 personajes alrededor de nivel 7</strong>. Desde el punto
              de vista del gobierno esto constituye una fuerza militar considerable.
            </p>
          </div>
          <aside class="info-box">
            <strong>Lectura del gobierno</strong>
            <span>Numeroso</span>
            <span>Difícil de controlar</span>
            <span>Historial de incidentes extremos</span>
          </aside>
        </div>

        <h3>Investigación sobre el gremio</h3>
        <p>
          La nueva administración contrató una investigación que encontró múltiples vínculos entre Per-Favore y acontecimientos
          extremadamente peligrosos. El caso más grave es su participación indirecta en el nacimiento de un dios del caos,
          evento que provocó múltiples muertes y consecuencias importantes.
        </p>
        <div class="callout">
          Para los jugadores puede sentirse como “otra aventura”. Para una administración, en cambio, forma parte de un patrón:
          cuando aparece una amenaza extraordinaria, miembros de Per-Favore suelen estar implicados de algún modo.
        </div>
      </section>

      <section class="wiki-section faction" id="guantelete" data-search="orden del guantelete paladines venganza watchers antiguos devoción corona dinero influencia">
        <div class="section-kicker">Facción</div>
        <h2>Orden del Guantelete</h2>
        <p>
          Organización con una enorme cantidad de dinero, recursos, poder e influencia. Sus filas incluyen muchos paladines,
          entre ellos juramentos de Venganza, Watchers, Antiguos, Devoción y Corona.
        </p>
        <p>Posee una estructura seria y un peso considerable dentro de Waterdeep.</p>
      </section>

      <section class="wiki-section faction" id="eclipse" data-search="guardia del eclipse grandes monstruos selectivo soberbios reputación supervisión">
        <div class="section-kicker">Facción</div>
        <h2>Guardia del Eclipse</h2>
        <p>
          Gremio de gran reputación, ingreso estricto y estándares considerablemente más altos que otras organizaciones.
          Suele recibir encargos relacionados con <strong>Grandes Monstruos</strong> y amenazas especialmente peligrosas.
        </p>
        <div class="tags large">
          <span>Selectivos</span><span>Disciplinados</span><span>Competentes</span><span>Soberbios</span>
        </div>
        <p>
          Muchos de sus miembros se consideran mejores que otros aventureros. Esa mezcla de competencia real y arrogancia
          los convierte en candidatos ideales para supervisar a Per-Favore y generar fricción sin necesidad de presentarlos
          inicialmente como villanos.
        </p>
      </section>

      <section class="wiki-section faction" id="lores" data-search="alianza de los lores organización política incompleta">
        <div class="section-kicker">Facción</div>
        <h2>Alianza de los Lores</h2>
        <p>
          Forma parte del panorama político y de organizaciones influyentes de la región. Su papel específico dentro de este
          conflicto todavía no está completamente desarrollado.
        </p>
      </section>

      <section class="wiki-section faction" id="candado" data-search="candado sangriento candando sangriento antiguo grupo seraphim oscuro">
        <div class="section-kicker">Facción</div>
        <h2>Candado / Candando Sangriento</h2>
        <p>
          Antiguo grupo que ya existía dentro del mundo y que actualmente trabaja junto a Per-Favore para enfrentar al Seraphim Oscuro.
        </p>
      </section>

      <section class="wiki-section" id="neverwinter" data-search="neverwinter cazadores demonios gremio desmantelado estable">
        <div class="section-kicker">Región</div>
        <h2>Neverwinter</h2>
        <p>
          Se encuentra en una situación bastante mejor que Waterdeep. Su principal problemática reciente estuvo relacionada
          con un gremio de cazadores de demonios que llegó a acumular demasiado poder y terminó siendo desmantelado.
        </p>
        <p>
          Para Dickson, Neverwinter funciona como precedente de lo que puede ocurrir cuando un gremio crece más allá de lo aceptable.
        </p>
      </section>

      <section class="wiki-section" id="baldurs" data-search="baldurs gate averno flaming fist puño llameante demonios deplorable militar">
        <div class="section-kicker">Región</div>
        <h2>Baldur's Gate</h2>
        <p>
          Años después de la Caída al Averno, la ciudad vive bajo una fuerte presencia militar de la Flaming Fist / Puño Llameante
          y además ha sufrido ataques demoníacos. Su situación general es descrita como deplorable.
        </p>
        <p>
          Para Dickson, Baldur's Gate muestra el otro extremo: lo que ocurre cuando una ciudad pierde el control de su seguridad.
        </p>
      </section>

      <section class="wiki-section" id="seraphim" data-search="seraphim oscuro inmortal asesinando personas amenaza per favore candado sangriento">
        <div class="section-kicker">Amenaza actual</div>
        <h2>El Seraphim Oscuro</h2>
        <p>
          Una entidad que ha estado asesinando personas y que ha demostrado ser aparentemente inmortal. Los métodos convencionales
          no han logrado eliminarlo de forma definitiva.
        </p>
        <div class="callout danger">
          Actualmente Per-Favore y Candado/Candando Sangriento intentan detenerlo.
        </div>
        <p>
          Para el gobierno, esto refuerza el patrón que alimenta la desconfianza: cada nueva amenaza de escala considerable vuelve
          a colocar a Per-Favore en el centro de los acontecimientos.
        </p>
      </section>

      <section class="wiki-section" id="supervision" data-search="supervisión 1 4 misiones cinco miembros guardia eclipse revisar daños objetos reglas">
        <div class="section-kicker">Operación</div>
        <h2>La supervisión de Per-Favore</h2>
        <p>
          Dickson no quiere comenzar con una guerra abierta. Su estrategia inicial es burocrática y militar: demostrar que ahora
          existe una autoridad por encima del gremio.
        </p>
        <div class="timeline mini">
          <div><strong>1–4 misiones</strong><span>Ventana inicial de intervención.</span></div>
          <div><strong>≈ 5 miembros</strong><span>Guardia del Eclipse recurrente.</span></div>
          <div><strong>Escalada gradual</strong><span>De revisión legítima a interferencia real.</span></div>
        </div>
        <h3>Acciones iniciales posibles</h3>
        <ul class="clean-list">
          <li>Revisar el trabajo realizado.</li>
          <li>Supervisar cómo se completa una misión.</li>
          <li>Evaluar daños colaterales.</li>
          <li>Comprobar objetos encontrados.</li>
          <li>Revisar recompensas.</li>
          <li>Verificar el cumplimiento de órdenes.</li>
          <li>Interferir progresivamente en decisiones.</li>
        </ul>
      </section>

      <section class="wiki-section" id="recompensas" data-search="confiscar objetos reducir recompensa daños custodia waterdeep">
        <div class="section-kicker">Escalada</div>
        <h2>Objetos, recompensas y autoridad</h2>
        <p>
          Una de las principales herramientas para generar conflicto será intervenir en las recompensas. Un objeto mágico puede
          ser declarado peligroso y quedar bajo custodia. Una recompensa puede reducirse tras considerar daños, incumplimientos,
          reparaciones u otros gastos.
        </p>
        <blockquote>
          “Este objeto representa un peligro y debe quedar bajo custodia.”
        </blockquote>
        <p>
          La intención es que los jugadores comiencen a sentir que la Guardia del Eclipse les está quitando aquello que se ganaron,
          mientras los supervisores creen sinceramente estar protegiendo a la ciudad.
        </p>
      </section>

      <section class="wiki-section" id="plan-dickson" data-search="verdadero plan dickson per favore guardia eclipse destruir debilitar reputación conflicto político">
        <div class="section-kicker">Conspiración política</div>
        <h2>El verdadero plan de Richard Dickson</h2>
        <p>
          El enfrentamiento entre Per-Favore y Guardia del Eclipse puede no ser un accidente. Dickson quiere que ambos gremios
          comiencen a odiarse y, eventualmente, se enfrenten.
        </p>
        <div class="impact-grid">
          <div><strong>Miembros</strong><span>Ambos bandos pierden combatientes.</span></div>
          <div><strong>Recursos</strong><span>Se consumen en un conflicto interno.</span></div>
          <div><strong>Reputación</strong><span>Queda dañada públicamente.</span></div>
          <div><strong>Justificación</strong><span>El gobierno obtiene argumentos para intervenir.</span></div>
        </div>
        <p>
          Incluso si un gremio gana, probablemente terminará demasiado debilitado para desafiar directamente al gobierno.
        </p>
      </section>

      <section class="wiki-section special" id="taxmen" data-search="tax men taxmen automatones metálicos reaving dispel critical role calamity control gobierno">
        <div class="section-kicker">Proyecto de seguridad</div>
        <h2>Los Tax-Men</h2>
        <p>
          El proyecto final de Dickson consiste en sustituir progresivamente a los gremios de aventureros como principal herramienta
          de protección de Waterdeep mediante guardianes/autómatas metálicos.
        </p>
        <div class="two-col">
          <div>
            <h3>Por qué resultan atractivos para el gobierno</h3>
            <ul class="clean-list">
              <li>No tienen ambiciones personales.</li>
              <li>No forman gremios.</li>
              <li>No exigen recompensas enormes.</li>
              <li>No acumulan influencia política.</li>
              <li>No cuestionan órdenes.</li>
              <li>Pueden producirse siguiendo un modelo.</li>
              <li>Responden directamente ante la administración.</li>
            </ul>
          </div>
          <aside class="info-box dark">
            <strong>Referencia</strong>
            <span>Inspirados en los Taxmen de Critical Role / EXU: Calamity.</span>
            <span>Concepto adaptado libremente a la campaña.</span>
          </aside>
        </div>
        <h3>Reaving Dispel</h3>
        <p>
          Entre las capacidades de referencia existe Reaving Dispel, una facultad capaz de disipar magia mientras daña al objetivo
          al arrancarle esa energía mágica. En esta campaña, el concepto puede adaptarse sin necesidad de replicar exactamente el canon.
        </p>
      </section>

      <section class="wiki-section" id="arco" data-search="estructura arco etapas supervision interferencia hostilidad provocacion enfrentamiento taxmen">
        <div class="section-kicker">Narrativa</div>
        <h2>Estructura del arco</h2>
        <div class="timeline">
          <article>
            <span>01</span>
            <div><h3>Supervisión</h3><p>Guardia del Eclipse aparece durante las misiones. Son molestos, pero aparentemente razonables.</p></div>
          </article>
          <article>
            <span>02</span>
            <div><h3>Interferencia</h3><p>Cuestionan decisiones, confiscan algún objeto, reducen recompensas e imponen reglas.</p></div>
          </article>
          <article>
            <span>03</span>
            <div><h3>Hostilidad</h3><p>Las discusiones empiezan a ser personales y ambos gremios construyen una visión negativa del otro.</p></div>
          </article>
          <article>
            <span>04</span>
            <div><h3>Provocación</h3><p>Una confiscación importante, un arresto o una misión especialmente mala hace explotar la tensión.</p></div>
          </article>
          <article>
            <span>05</span>
            <div><h3>Per-Favore vs Guardia del Eclipse</h3><p>Se produce un enfrentamiento serio entre ambos gremios.</p></div>
          </article>
          <article>
            <span>06</span>
            <div><h3>Dickson obtiene su argumento</h3><p>El gobierno presenta la pelea como prueba de que los gremios armados son demasiado peligrosos.</p></div>
          </article>
          <article>
            <span>07</span>
            <div><h3>Tax-Men</h3><p>La administración presenta la nueva fuerza metálica como alternativa estable y controlable.</p></div>
          </article>
        </div>
      </section>

      <section class="wiki-section" id="ironia" data-search="ironia aventureros independientes control gubernamental dickson no villano metodo">
        <div class="section-kicker">Tema central</div>
        <h2>La ironía de la historia</h2>
        <p>
          Lo que al principio parece un conflicto entre dos gremios termina revelando un choque mucho mayor:
          <strong>aventureros independientes vs. control gubernamental</strong>.
        </p>
        <p>
          Dickson no necesita estar equivocado en su diagnóstico. Waterdeep sí ha sufrido muchísimo, Per-Favore sí tiene un historial
          peligroso y los gremios sí acumulan poder. El conflicto moral aparece en el método: hasta dónde está dispuesto a llegar para
          imponer su solución.
        </p>
        <div class="callout">
          El objetivo es que la pregunta no sea “¿Dickson es malo?”, sino “¿es razonable querer controlar a los gremios, y qué límites
          debería tener ese control?”.
        </div>
      </section>

      <footer>
        <strong>Wiki de Waterdeep</strong>
        <span>Documento de campaña · Favor de Jack</span>
      </footer>
    </main>
  </div>

  <button id="toTop" aria-label="Volver arriba">↑</button>
  <script src="app.js"></script>
</body>
</html>
