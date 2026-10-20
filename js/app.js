/**
 * EcoTrack - Lógica del Cliente Frontend
 * Asignatura: Aplicaciones Móviles y Cloud Computing (UPSO)
 * 
 * Cumple requisito R3:
 * - Comunicación asíncrona mediante Fetch API sin recargar la página.
 * - Validación estricta del estado de respuesta (respuesta.ok) y reporte amigable de errores.
 * - CRUD dinámico en el DOM: creación, lectura, actualización y eliminación.
 * - Integración con Leaflet (OpenStreetMap) y Chart.js.
 */

document.addEventListener("DOMContentLoaded", () => {
  // -------------------------------------------------------------
  // 1. Constantes y Endpoints
  // -------------------------------------------------------------
  const API_MEDICIONES = "api/mediciones.php";
  const API_SERVICIOS = "api/servicios.php";

  // Estado local de la aplicación
  let medicionesLocales = [];
  let mapaLeaflet = null;
  let marcadoresMapa = [];
  let marcadorTemporal = null;
  let graficoTendencias = null;

  // Elementos del DOM
  const formMedicion       = document.getElementById("formulario-medicion");
  const campoId            = document.getElementById("campo-id");
  const campoUbicacion     = document.getElementById("campo-ubicacion");
  const campoTemperatura   = document.getElementById("campo-temperatura");
  const campoHumedad       = document.getElementById("campo-humedad");
  const campoFecha         = document.getElementById("campo-fecha");
  const campoLatitud       = document.getElementById("campo-latitud");
  const campoLongitud      = document.getElementById("campo-longitud");
  const campoObservaciones = document.getElementById("campo-observaciones");
  
  const btnGuardar         = document.getElementById("btn-guardar");
  const btnGuardarTexto    = document.getElementById("btn-guardar-texto");
  const btnCancelar        = document.getElementById("btn-cancelar");
  const badgeModoForm      = document.getElementById("badge-modo-form");
  const alertaForm         = document.getElementById("alerta-form");
  
  const cuerpoTabla        = document.getElementById("cuerpo-tabla");
  const contadorMediciones = document.getElementById("contador-mediciones");
  const inputBuscar        = document.getElementById("input-buscar");
  const btnRecargar        = document.getElementById("btn-recargar");
  
  const btnActualizarClima   = document.getElementById("btn-actualizar-clima");
  const bannerDegradacion    = document.getElementById("banner-degradacion");
  const climaUbicacionNombre = document.getElementById("clima-ubicacion-nombre");
  const climaTemp            = document.getElementById("clima-temp");
  const climaHum             = document.getElementById("clima-hum");
  const climaViento          = document.getElementById("clima-viento");
  const climaHora            = document.getElementById("clima-hora");
  const toastEl              = document.getElementById("toast");

  // -------------------------------------------------------------
  // 2. Notificaciones Toast y Alertas
  // -------------------------------------------------------------
  function mostrarToast(mensaje, tipo = "success") {
    toastEl.textContent = mensaje;
    toastEl.className = "toast show " + (tipo === "error" ? "toast-error" : "toast-success");
    setTimeout(() => {
      toastEl.className = "toast";
    }, 4000);
  }

  function mostrarAlertaForm(mensaje, tipo = "danger") {
    alertaForm.textContent = mensaje;
    alertaForm.className = `alert alert-${tipo}`;
    alertaForm.classList.remove("d-none");
  }

  function limpiarAlertaForm() {
    alertaForm.textContent = "";
    alertaForm.className = "alert d-none";
  }

  // Sanitización de texto para evitar XSS en renderizado dinámico
  function escaparHTML(texto) {
    if (texto === null || texto === undefined) return "";
    const div = document.createElement("div");
    div.textContent = String(texto);
    return div.innerHTML;
  }

  // -------------------------------------------------------------
  // 3. Inicialización de Mapa Leaflet (OpenStreetMap)
  // -------------------------------------------------------------
  function inicializarMapa() {
    try {
      const coordCentro = [-38.3739, -60.2798]; // Tres Arroyos / Región Sudoeste
      mapaLeaflet = L.map("mapa-contenedor").setView(coordCentro, 8);

      L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a>'
      }).addTo(mapaLeaflet);

      // Evento de clic en el mapa para autocompletar coordenadas en el formulario
      mapaLeaflet.on("click", (e) => {
        const { lat, lng } = e.latlng;
        campoLatitud.value = lat.toFixed(5);
        campoLongitud.value = lng.toFixed(5);

        if (marcadorTemporal) {
          mapaLeaflet.removeLayer(marcadorTemporal);
        }
        marcadorTemporal = L.marker([lat, lng])
          .addTo(mapaLeaflet)
          .bindPopup(`Punto seleccionado:<br>${lat.toFixed(4)}, ${lng.toFixed(4)}`)
          .openPopup();

        mostrarToast("Coordenadas actualizadas desde el mapa.");
      });
    } catch (err) {
      console.warn("No se pudo inicializar Leaflet:", err);
    }
  }

  function actualizarMarcadoresMapa(mediciones) {
    if (!mapaLeaflet) return;

    // Limpiar marcadores anteriores
    marcadoresMapa.forEach((m) => mapaLeaflet.removeLayer(m));
    marcadoresMapa = [];

    mediciones.forEach((m) => {
      if (m.latitud && m.longitud) {
        const marcador = L.marker([m.latitud, m.longitud]).addTo(mapaLeaflet);
        marcador.bindPopup(`
          <strong>${escaparHTML(m.ubicacion)}</strong><br>
          🌡️ ${m.temperatura} °C · 💧 ${m.humedad}%<br>
          <small>${m.fecha}</small>
        `);
        marcadoresMapa.push(marcador);
      }
    });
  }

  // -------------------------------------------------------------
  // 4. Inicialización de Gráfico Básico (Chart.js)
  // -------------------------------------------------------------
  function inicializarGrafico() {
    const ctx = document.getElementById("grafico-mediciones");
    if (!ctx || typeof Chart === "undefined") return;

    graficoTendencias = new Chart(ctx, {
      type: "line",
      data: {
        labels: [],
        datasets: [
          {
            label: "Temperatura (°C)",
            data: [],
            borderColor: "#ea580c",
            backgroundColor: "rgba(234, 88, 12, 0.1)",
            tension: 0.3,
            fill: true
          },
          {
            label: "Humedad (%)",
            data: [],
            borderColor: "#0284c7",
            backgroundColor: "rgba(2, 132, 199, 0.1)",
            tension: 0.3,
            fill: true
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: "top" }
        },
        scales: {
          y: { beginAtZero: false }
        }
      }
    });
  }

  function actualizarGrafico(mediciones) {
    if (!graficoTendencias) return;

    // Invertir para mostrar de más antigua a más reciente en el eje horizontal
    const ordenadas = [...mediciones].reverse().slice(-10);

    graficoTendencias.data.labels = ordenadas.map((m) => {
      const parts = m.ubicacion.split(" - ");
      return parts[0];
    });
    graficoTendencias.data.datasets[0].data = ordenadas.map((m) => m.temperatura);
    graficoTendencias.data.datasets[1].data = ordenadas.map((m) => m.humedad);
    graficoTendencias.update();
  }

  // -------------------------------------------------------------
  // 5. Consumo del Servicio Externo de Clima (R4 & R5)
  // -------------------------------------------------------------
  async function cargarClimaReferencia(lat = -38.3739, lon = -60.2798) {
    climaHora.textContent = "Consultando...";
    try {
      const url = `${API_SERVICIOS}?accion=clima&lat=${lat}&lon=${lon}`;
      const respuesta = await fetch(url);

      if (!respuesta.ok) {
        throw new Error(`El servidor respondió con código ${respuesta.status}`);
      }

      const info = await respuesta.json();

      if (info.degradado || !info.disponible) {
        // R5: Degradación elegante detectada
        bannerDegradacion.classList.remove("d-none");
        climaTemp.textContent = "-- °C";
        climaHum.textContent = "-- %";
        climaViento.textContent = "-- km/h";
        climaHora.textContent = "Servicio meteorológico degradado";
      } else {
        bannerDegradacion.classList.add("d-none");
        const datos = info.datos;
        climaTemp.textContent = `${datos.temperatura.toFixed(1)} °C`;
        climaHum.textContent = `${datos.humedad.toFixed(0)} %`;
        climaViento.textContent = `${datos.viento_kmh.toFixed(1)} km/h`;
        climaHora.textContent = `Actualizado: ${datos.fecha_hora}`;
      }
    } catch (error) {
      console.warn("Fallo al contactar el servicio de clima:", error);
      bannerDegradacion.classList.remove("d-none");
      climaHora.textContent = "Error al conectar con la API de clima";
    }
  }

  // -------------------------------------------------------------
  // 6. CRUD de Mediciones Ambientales (R1, R2, R3)
  // -------------------------------------------------------------

  // GET: Cargar listado de mediciones
  async function cargarMediciones(terminoBusqueda = "") {
    cuerpoTabla.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted">Cargando mediciones desde la API...</td></tr>`;

    try {
      const url = terminoBusqueda 
        ? `${API_MEDICIONES}?buscar=${encodeURIComponent(terminoBusqueda)}` 
        : API_MEDICIONES;

      const respuesta = await fetch(url);

      // Verificación de respuesta HTTP exitosa (R3: respuesta.ok)
      if (!respuesta.ok) {
        const errorData = await respuesta.json().catch(() => ({}));
        throw new Error(errorData.mensaje || `Error HTTP ${respuesta.status}`);
      }

      const resultado = await respuesta.json();
      medicionesLocales = resultado.datos || [];

      renderizarTabla(medicionesLocales);
      actualizarMarcadoresMapa(medicionesLocales);
      actualizarGrafico(medicionesLocales);

      contadorMediciones.textContent = `${medicionesLocales.length} medición(es) registrada(s).`;
    } catch (error) {
      console.error("Error al obtener mediciones:", error);
      cuerpoTabla.innerHTML = `
        <tr>
          <td colspan="7" class="text-center py-4 text-danger">
            ⚠️ No se pudieron cargar las mediciones: ${escaparHTML(error.message)}
          </td>
        </tr>
      `;
      mostrarToast("Error al obtener datos de la API.", "error");
    }
  }

  // Renderizar filas de la tabla
  function renderizarTabla(lista) {
    if (lista.length === 0) {
      cuerpoTabla.innerHTML = `
        <tr>
          <td colspan="7" class="text-center py-4 text-muted">
            No hay mediciones ambientales registradas aún. ¡Ingresá la primera con el formulario!
          </td>
        </tr>
      `;
      return;
    }

    cuerpoTabla.innerHTML = "";

    lista.forEach((m) => {
      const tr = document.createElement("tr");

      // Formateo de fecha legible
      const fechaObj = new Date(m.fecha);
      const fechaFormateada = isNaN(fechaObj.getTime())
        ? m.fecha
        : fechaObj.toLocaleString("es-AR", {
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit"
          });

      tr.innerHTML = `
        <td><strong>#${m.id}</strong></td>
        <td>
          <strong>${escaparHTML(m.ubicacion)}</strong>
          ${m.latitud && m.longitud ? `<br><span class="text-sm text-muted">📍 ${Number(m.latitud).toFixed(2)}, ${Number(m.longitud).toFixed(2)}</span>` : ""}
        </td>
        <td><span class="badge-temp">${Number(m.temperatura).toFixed(1)} °C</span></td>
        <td><span class="badge-hum">${Number(m.humedad).toFixed(1)} %</span></td>
        <td><span class="text-sm">${fechaFormateada}</span></td>
        <td><span class="text-sm text-muted">${escaparHTML(m.observaciones || "—")}</span></td>
        <td class="text-center">
          <div class="table-actions">
            <button class="btn btn-outline btn-sm btn-editar" data-id="${m.id}" title="Editar medición">
              ✏️ Editar
            </button>
            <button class="btn btn-danger-outline btn-sm btn-eliminar" data-id="${m.id}" data-ubicacion="${escaparHTML(m.ubicacion)}" title="Eliminar medición">
              🗑️ Borrar
            </button>
          </div>
        </td>
      `;

      cuerpoTabla.appendChild(tr);
    });
  }

  // POST o PUT: Envío del formulario
  formMedicion.addEventListener("submit", async (e) => {
    e.preventDefault();
    limpiarAlertaForm();

    const id = campoId.value.trim();
    const esEdicion = id !== "";

    // Validación básica en el frontend
    const ubicacion = campoUbicacion.value.trim();
    const temperatura = campoTemperatura.value.trim();
    const humedad = campoHumedad.value.trim();

    if (ubicacion === "" || temperatura === "" || humedad === "") {
      mostrarAlertaForm("Por favor completá los campos obligatorios (*).", "danger");
      return;
    }

    // Armado del payload
    const payload = {
      ubicacion: ubicacion,
      temperatura: parseFloat(temperatura),
      humedad: parseFloat(humedad),
      fecha: campoFecha.value ? campoFecha.value.replace("T", " ") : null,
      latitud: campoLatitud.value !== "" ? parseFloat(campoLatitud.value) : null,
      longitud: campoLongitud.value !== "" ? parseFloat(campoLongitud.value) : null,
      observaciones: campoObservaciones.value.trim()
    };

    btnGuardar.disabled = true;
    btnGuardarTexto.textContent = esEdicion ? "Actualizando..." : "Guardando...";

    try {
      const url = esEdicion ? `${API_MEDICIONES}?id=${id}` : API_MEDICIONES;
      const metodo = esEdicion ? "PUT" : "POST";

      const respuesta = await fetch(url, {
        method: metodo,
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json"
        },
        body: JSON.stringify(payload)
      });

      const data = await respuesta.json().catch(() => ({}));

      // R3: Inspección de respuesta.ok
      if (!respuesta.ok) {
        let detalleError = data.mensaje || `Error en el servidor (${respuesta.status})`;
        if (data.errores) {
          detalleError += ": " + Object.values(data.errores).join(" · ");
        }
        throw new Error(detalleError);
      }

      // Éxito
      if (data.aviso_servicio) {
        mostrarToast(`${data.mensaje || (esEdicion ? "Medición modificada." : "Medición registrada.")} (${data.aviso_servicio})`);
      } else {
        mostrarToast(data.mensaje || (esEdicion ? "Medición modificada." : "Medición registrada."));
      }

      // Sincronización de mapa y clima de referencia si hay coordenadas válidas
      if (data.datos && data.datos.latitud !== null && data.datos.longitud !== null &&
          !isNaN(Number(data.datos.latitud)) && !isNaN(Number(data.datos.longitud))) {
        const latVal = Number(data.datos.latitud);
        const lonVal = Number(data.datos.longitud);

        if (mapaLeaflet) {
          mapaLeaflet.setView([latVal, lonVal], 11);
        }
        cargarClimaReferencia(latVal, lonVal);
        if (climaUbicacionNombre && data.datos.ubicacion) {
          climaUbicacionNombre.textContent = `Estación Meteorológica: ${data.datos.ubicacion}`;
        }
      }

      resetearFormulario();
      await cargarMediciones();

    } catch (err) {
      console.error("Error al procesar formulario:", err);
      mostrarAlertaForm(err.message, "danger");
      mostrarToast("No se pudo completar la operación.", "error");
    } finally {
      btnGuardar.disabled = false;
      btnGuardarTexto.textContent = esEdicion ? "Actualizar Medición" : "Guardar Medición";
    }
  });

  // Manejo de clics en la tabla (Delegación de eventos: Editar y Eliminar)
  cuerpoTabla.addEventListener("click", async (e) => {
    const btnEditar = e.target.closest(".btn-editar");
    const btnEliminar = e.target.closest(".btn-eliminar");

    // ACCIÓN: EDITAR (Carga datos en el formulario)
    if (btnEditar) {
      const id = btnEditar.getAttribute("data-id");
      const medicion = medicionesLocales.find((m) => String(m.id) === String(id));
      if (!medicion) return;

      campoId.value = medicion.id;
      campoUbicacion.value = medicion.ubicacion;
      campoTemperatura.value = medicion.temperatura;
      campoHumedad.value = medicion.humedad;
      
      // Formateo de fecha para datetime-local (YYYY-MM-DDTHH:MM)
      if (medicion.fecha) {
        const d = new Date(medicion.fecha);
        if (!isNaN(d.getTime())) {
          const iso = new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
          campoFecha.value = iso;
        }
      }
      campoLatitud.value = medicion.latitud !== null ? medicion.latitud : "";
      campoLongitud.value = medicion.longitud !== null ? medicion.longitud : "";
      campoObservaciones.value = medicion.observaciones || "";

      // Ajuste de interfaz a modo edición
      badgeModoForm.textContent = `Modificando #${medicion.id}`;
      badgeModoForm.className = "badge badge-warning";
      btnGuardarTexto.textContent = "Actualizar Medición";
      btnCancelar.classList.remove("d-none");
      limpiarAlertaForm();

      formMedicion.scrollIntoView({ behavior: "smooth" });
    }

    // ACCIÓN: ELIMINAR (DELETE con confirmación)
    if (btnEliminar) {
      const id = btnEliminar.getAttribute("data-id");
      const ubicacion = btnEliminar.getAttribute("data-ubicacion");

      const confirmacion = confirm(`¿Estás seguro de eliminar la medición #${id} (${ubicacion})?`);
      if (!confirmacion) return;

      try {
        const respuesta = await fetch(`${API_MEDICIONES}?id=${id}`, {
          method: "DELETE",
          headers: { "Accept": "application/json" }
        });

        const data = await respuesta.json().catch(() => ({}));

        if (!respuesta.ok) {
          throw new Error(data.mensaje || `Error al eliminar (${respuesta.status})`);
        }

        mostrarToast(data.mensaje || "Medición eliminada correctamente.");
        await cargarMediciones();

      } catch (err) {
        console.error("Error al eliminar:", err);
        mostrarToast(err.message, "error");
      }
    }
  });

  // Cancelar edición
  btnCancelar.addEventListener("click", () => {
    resetearFormulario();
  });

  function resetearFormulario() {
    formMedicion.reset();
    campoId.value = "";
    badgeModoForm.textContent = "Nuevo Registro";
    badgeModoForm.className = "badge badge-success";
    btnGuardarTexto.textContent = "Guardar Medición";
    btnCancelar.classList.add("d-none");
    limpiarAlertaForm();

    if (marcadorTemporal && mapaLeaflet) {
      mapaLeaflet.removeLayer(marcadorTemporal);
      marcadorTemporal = null;
    }
  }

  // Búsqueda / Filtro en tiempo real
  let debounceTimeout = null;
  inputBuscar.addEventListener("input", () => {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
      cargarMediciones(inputBuscar.value.trim());
    }, 300);
  });

  btnRecargar.addEventListener("click", () => {
    inputBuscar.value = "";
    cargarMediciones();
  });

  btnActualizarClima.addEventListener("click", () => {
    cargarClimaReferencia();
  });

  // -------------------------------------------------------------
  // 7. Arranque Inicial
  // -------------------------------------------------------------
  inicializarMapa();
  inicializarGrafico();
  cargarClimaReferencia();
  cargarMediciones();
});
