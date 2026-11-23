import { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import '../css/reporte.css';

/**
 * Pantalla del reporte por equipo y rango de fechas.
 *
 * Flujo: login (POST /api/login) -> carga de equipos (GET /api/equipos)
 * -> reporte (GET /api/equipos/{id}/reporte?desde=...&hasta=...).
 * El token se guarda en localStorage: al recargar la página la sesión
 * se mantiene hasta que el usuario cierre sesión o el token venza (401).
 */

const formatoCLP = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 });
const formatoNumero = new Intl.NumberFormat('es-CL', { maximumFractionDigits: 2 });

const HOY = new Date();
const HACE_90_DIAS = new Date(HOY.getTime() - 90 * 24 * 60 * 60 * 1000);

const CLAVE_TOKEN = 'token';

/** Lee el token guardado. Si el navegador bloquea el almacenamiento, devuelve null. */
function leerToken() {
    try {
        return localStorage.getItem(CLAVE_TOKEN);
    } catch {
        return null;
    }
}

/** Guarda el token, o lo borra si recibe null. */
function guardarToken(token) {
    try {
        if (token) {
            localStorage.setItem(CLAVE_TOKEN, token);
        } else {
            localStorage.removeItem(CLAVE_TOKEN);
        }
    } catch {
        // Sin almacenamiento disponible: la sesión dura hasta recargar.
    }
}

/** Fecha local como Y-m-d (toISOString usaría UTC y podría correr el día). */
function aYmd(fecha) {
    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
    const dia = String(fecha.getDate()).padStart(2, '0');
    return `${fecha.getFullYear()}-${mes}-${dia}`;
}

/** Error de la API, con el código HTTP para poder reaccionar (por ejemplo, a un 401). */
class ErrorApi extends Error {
    constructor(estado, mensaje) {
        super(mensaje);
        this.estado = estado;
    }
}

/**
 * Llama a la API y devuelve el JSON. Si la respuesta no es exitosa lanza
 * un ErrorApi con el primer mensaje de validación (422) o el mensaje general.
 */
async function api(ruta, { token, metodo = 'GET', cuerpo } = {}) {
    const respuesta = await fetch(`/api${ruta}`, {
        method: metodo,
        headers: {
            Accept: 'application/json',
            ...(cuerpo && { 'Content-Type': 'application/json' }),
            ...(token && { Authorization: `Bearer ${token}` }),
        },
        body: cuerpo ? JSON.stringify(cuerpo) : undefined,
    });

    // El logout responde 204 sin cuerpo, por eso el catch.
    const datos = await respuesta.json().catch(() => ({}));

    if (!respuesta.ok) {
        const primerError = datos.errors ? Object.values(datos.errors)[0][0] : null;
        throw new ErrorApi(respuesta.status, primerError ?? datos.message ?? 'Error inesperado');
    }

    return datos;
}

/** Formulario de inicio de sesión. Entrega el token a través de alIngresar. */
function Login({ alIngresar }) {
    // Datos del usuario de ejemplo del seeder, para probar más rápido.
    const [email, setEmail] = useState('admin@construckit.test');
    const [password, setPassword] = useState('password');
    const [error, setError] = useState(null);
    const [cargando, setCargando] = useState(false);

    async function enviar(evento) {
        evento.preventDefault();
        setError(null);
        setCargando(true);

        try {
            const { token } = await api('/login', { metodo: 'POST', cuerpo: { email, password } });
            alIngresar(token);
        } catch (e) {
            setError(e.message);
        } finally {
            setCargando(false);
        }
    }

    return (
        <form className="panel" onSubmit={enviar}>
            <h1>Iniciar sesión</h1>
            <label>
                Correo
                <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
            </label>
            <label>
                Clave
                <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />
            </label>
            {error && <p className="error">{error}</p>}
            <button type="submit" disabled={cargando}>{cargando ? 'Ingresando…' : 'Ingresar'}</button>
        </form>
    );
}

/** Una cifra del reporte. */
function Tarjeta({ titulo, valor, nota }) {
    return (
        <div className="tarjeta">
            <span className="tarjeta-titulo">{titulo}</span>
            <strong className="tarjeta-valor">{valor}</strong>
            {nota && <small>{nota}</small>}
        </div>
    );
}

/** Selector de equipo y fechas, y las cifras del reporte. */
function Reporte({ token, alSalir }) {
    const [equipos, setEquipos] = useState([]);
    const [equipoId, setEquipoId] = useState('');
    const [desde, setDesde] = useState(aYmd(HACE_90_DIAS));
    const [hasta, setHasta] = useState(aYmd(HOY));
    const [reporte, setReporte] = useState(null);
    const [error, setError] = useState(null);
    const [cargando, setCargando] = useState(false);

    /** Un 401 significa que el token ya no sirve: se vuelve al login. */
    function manejarError(e) {
        if (e.estado === 401) {
            alSalir();
        } else {
            setError(e.message);
        }
    }

    // Carga todos los equipos para el selector, recorriendo las páginas.
    useEffect(() => {
        async function cargarEquipos() {
            try {
                const todos = [];
                let pagina = 1;
                let ultima = 1;

                do {
                    const respuesta = await api(`/equipos?page=${pagina}`, { token });
                    todos.push(...respuesta.data);
                    ultima = respuesta.last_page;
                    pagina++;
                } while (pagina <= ultima);

                setEquipos(todos);
                if (todos.length > 0) setEquipoId(String(todos[0].id));
            } catch (e) {
                manejarError(e);
            }
        }

        cargarEquipos();
    }, [token]);

    async function generar(evento) {
        evento.preventDefault();
        setError(null);
        setReporte(null);
        setCargando(true);

        try {
            const consulta = new URLSearchParams({ desde, hasta });
            setReporte(await api(`/equipos/${equipoId}/reporte?${consulta}`, { token }));
        } catch (e) {
            manejarError(e);
        } finally {
            setCargando(false);
        }
    }

    return (
        <div className="pagina">
            <header className="encabezado">
                <h1>Reporte por equipo</h1>
                <button type="button" className="secundario" onClick={alSalir}>Cerrar sesión</button>
            </header>

            <form className="panel filtros" onSubmit={generar}>
                <label>
                    Equipo
                    <select value={equipoId} onChange={(e) => setEquipoId(e.target.value)} required>
                        {equipos.map((equipo) => (
                            <option key={equipo.id} value={equipo.id}>{equipo.codigo}</option>
                        ))}
                    </select>
                </label>
                <label>
                    Desde
                    <input type="date" value={desde} onChange={(e) => setDesde(e.target.value)} required />
                </label>
                <label>
                    Hasta
                    <input type="date" value={hasta} onChange={(e) => setHasta(e.target.value)} required />
                </label>
                <button type="submit" disabled={cargando || !equipoId}>
                    {cargando ? 'Calculando…' : 'Generar reporte'}
                </button>
            </form>

            {error && <p className="error">{error}</p>}

            {reporte && (
                <section>
                    <p className="resumen">
                        {reporte.equipo.codigo}, del {reporte.periodo.desde} al {reporte.periodo.hasta}
                        {' · '}{reporte.cargas_consideradas} cargas consideradas
                    </p>
                    <div className="tarjetas">
                        <Tarjeta titulo="Litros totales" valor={`${formatoNumero.format(reporte.litros_totales)} L`} />
                        <Tarjeta titulo="Gasto en combustible" valor={formatoCLP.format(reporte.gasto_combustible_clp)} />
                        <Tarjeta titulo="Gasto en mantenciones" valor={formatoCLP.format(reporte.gasto_mantenciones_clp)} />
                        <Tarjeta titulo="Horas de uso" valor={`${formatoNumero.format(reporte.horas_de_uso)} h`} />
                        <Tarjeta
                            titulo="Rendimiento"
                            valor={reporte.rendimiento_l_por_hora === null
                                ? 'Sin datos suficientes'
                                : `${formatoNumero.format(reporte.rendimiento_l_por_hora)} L/h`}
                            nota={reporte.rendimiento_l_por_hora === null
                                ? 'Se necesitan al menos 2 cargas con horas de uso'
                                : null}
                        />
                    </div>
                </section>
            )}
        </div>
    );
}

/** Muestra el login o el reporte según haya o no un token guardado. */
function App() {
    // Se lee del almacenamiento al iniciar, así la sesión sobrevive a recargar la página.
    const [token, setToken] = useState(leerToken);

    function ingresar(nuevoToken) {
        guardarToken(nuevoToken);
        setToken(nuevoToken);
    }

    /** Revoca el token en el servidor (si falla no importa), lo borra y vuelve al login. */
    async function salir() {
        if (token) {
            await api('/logout', { metodo: 'POST', token }).catch(() => {});
        }
        guardarToken(null);
        setToken(null);
    }

    return token
        ? <Reporte token={token} alSalir={salir} />
        : <Login alIngresar={ingresar} />;
}

createRoot(document.getElementById('root')).render(<App />);