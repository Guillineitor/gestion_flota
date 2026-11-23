import { useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Navigate, NavLink, Route, Routes, useLocation, useNavigate, useParams } from 'react-router-dom';
import '../css/reporte.css';

/**
 * Pantalla principal autenticada: CRUD de equipos y reporte por equipo/rango.
 *
 * Rutas del navegador (React Router): /login, /equipos, /equipos/nuevo,
 * /equipos/:id/editar y /reportes.
 * Flujo: login (POST /api/login) -> gestión de equipos (GET/POST/PUT/DELETE /api/equipos)
 * y/o reporte (GET /api/equipos/{id}/reporte?desde=...&hasta=...).
 * El token se guarda en localStorage para mantener la sesión al recargar.
 */

const formatoCLP = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 });
const formatoNumero = new Intl.NumberFormat('es-CL', { maximumFractionDigits: 2 });

const HOY = new Date();
const HACE_90_DIAS = new Date(HOY.getTime() - 90 * 24 * 60 * 60 * 1000);

const CLAVE_TOKEN = 'token';

const TIPOS_EQUIPO = [
    { value: 'camion', label: 'Camión' },
    { value: 'excavadora', label: 'Excavadora' },
    { value: 'generador', label: 'Generador' },
];

const ESTADOS_EQUIPO = [
    { value: 'operativo', label: 'Operativo' },
    { value: 'en_mantencion', label: 'En mantención' },
    { value: 'fuera_de_servicio', label: 'Fuera de servicio' },
];

const EQUIPO_VACIO = {
    codigo: '',
    tipo: '',
    patente: '',
    marca: '',
    modelo: '',
    horometro_actual: '0',
    estado: 'operativo',
};

function leerToken() {
    try {
        return localStorage.getItem(CLAVE_TOKEN);
    } catch {
        return null;
    }
}

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

function aYmd(fecha) {
    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
    const dia = String(fecha.getDate()).padStart(2, '0');
    return `${fecha.getFullYear()}-${mes}-${dia}`;
}

/** Error de la API, con código HTTP y errores de validación por campo. */
class ErrorApi extends Error {
    constructor(estado, mensaje, errores = null) {
        super(mensaje);
        this.estado = estado;
        this.errores = errores;
    }
}

/**
 * Llama a la API y devuelve el JSON. Si falla lanza ErrorApi con el mensaje
 * general y, en 422, los errores por campo.
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

    const datos = await respuesta.json().catch(() => ({}));

    if (!respuesta.ok) {
        const primerError = datos.errors ? Object.values(datos.errors)[0][0] : null;
        throw new ErrorApi(respuesta.status, primerError ?? datos.message ?? 'Error inesperado', datos.errors ?? null);
    }

    return datos;
}

/** Valida los datos del equipo en el cliente (refleja reglas del backend). */
function validarEquipo(valores, { esEdicion, horometroMinimo = 0 }) {
    const errores = {};
    const codigo = valores.codigo.trim();
    const marca = valores.marca.trim();
    const modelo = valores.modelo.trim();
    const patente = (valores.patente ?? '').trim();

    if (!esEdicion || valores.codigo !== undefined) {
        if (!codigo) errores.codigo = 'El código es obligatorio.';
        else if (codigo.length > 50) errores.codigo = 'El código no puede superar los 50 caracteres.';
    }

    if (!esEdicion || valores.tipo !== undefined) {
        if (!valores.tipo) errores.tipo = 'El tipo es obligatorio.';
        else if (!TIPOS_EQUIPO.some((t) => t.value === valores.tipo)) errores.tipo = 'El tipo seleccionado no es válido.';
    }

    if (patente.length > 10) errores.patente = 'La patente no puede superar los 10 caracteres.';

    if (!esEdicion || valores.marca !== undefined) {
        if (!marca) errores.marca = 'La marca es obligatoria.';
        else if (marca.length > 100) errores.marca = 'La marca no puede superar los 100 caracteres.';
    }

    if (!esEdicion || valores.modelo !== undefined) {
        if (!modelo) errores.modelo = 'El modelo es obligatorio.';
        else if (modelo.length > 100) errores.modelo = 'El modelo no puede superar los 100 caracteres.';
    }

    if (valores.horometro_actual !== '' && valores.horometro_actual !== undefined && valores.horometro_actual !== null) {
        const numero = Number(valores.horometro_actual);
        if (Number.isNaN(numero)) {
            errores.horometro_actual = 'El horómetro debe ser un número.';
        } else if (numero < horometroMinimo) {
            errores.horometro_actual = esEdicion
                ? `El horómetro no puede ser menor al actual (${formatoNumero.format(horometroMinimo)}).`
                : 'El horómetro no puede ser negativo.';
        }
    }

    if (valores.estado && !ESTADOS_EQUIPO.some((e) => e.value === valores.estado)) {
        errores.estado = 'El estado seleccionado no es válido.';
    }

    return errores;
}

function etiquetaTipo(valor) {
    return TIPOS_EQUIPO.find((t) => t.value === valor)?.label ?? valor;
}

function etiquetaEstado(valor) {
    return ESTADOS_EQUIPO.find((e) => e.value === valor)?.label ?? valor;
}

function aErroresCampos(erroresApi) {
    if (!erroresApi) return {};
    return Object.fromEntries(Object.entries(erroresApi).map(([campo, mensajes]) => [campo, mensajes[0]]));
}

function Login({ alIngresar }) {
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

function CampoError({ mensaje }) {
    if (!mensaje) return null;
    return <small className="error-campo">{mensaje}</small>;
}

/** Formulario de creación/edición de un equipo. */
function FormularioEquipo({ inicial, esEdicion, horometroMinimo = 0, alGuardar, alCancelar, guardando }) {
    const [valores, setValores] = useState(inicial);
    const [errores, setErrores] = useState({});
    const [errorGeneral, setErrorGeneral] = useState(null);

    useEffect(() => {
        setValores(inicial);
        setErrores({});
        setErrorGeneral(null);
    }, [inicial]);

    function actualizar(campo, valor) {
        setValores((prev) => ({ ...prev, [campo]: valor }));
        setErrores((prev) => ({ ...prev, [campo]: null }));
    }

    async function enviar(evento) {
        evento.preventDefault();
        setErrorGeneral(null);

        const locales = validarEquipo(valores, { esEdicion, horometroMinimo });
        if (Object.keys(locales).length > 0) {
            setErrores(locales);
            return;
        }

        const cuerpo = {
            codigo: valores.codigo.trim(),
            tipo: valores.tipo,
            patente: (valores.patente ?? '').trim() || null,
            marca: valores.marca.trim(),
            modelo: valores.modelo.trim(),
            estado: valores.estado,
        };

        if (valores.horometro_actual !== '' && valores.horometro_actual !== undefined) {
            cuerpo.horometro_actual = Number(valores.horometro_actual);
        }

        try {
            await alGuardar(cuerpo);
        } catch (e) {
            setErrores(aErroresCampos(e.errores));
            setErrorGeneral(e.message);
        }
    }

    return (
        <form className="panel formulario-equipo" onSubmit={enviar} noValidate>
            <h2>{esEdicion ? 'Editar equipo' : 'Nuevo equipo'}</h2>
            <div className="grid-formulario">
                <label>
                    Código *
                    <input value={valores.codigo} onChange={(e) => actualizar('codigo', e.target.value)} maxLength={50} />
                    <CampoError mensaje={errores.codigo} />
                </label>
                <label>
                    Tipo *
                    <select value={valores.tipo} onChange={(e) => actualizar('tipo', e.target.value)}>
                        <option value="">Seleccionar…</option>
                        {TIPOS_EQUIPO.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                    </select>
                    <CampoError mensaje={errores.tipo} />
                </label>
                <label>
                    Patente
                    <input value={valores.patente ?? ''} onChange={(e) => actualizar('patente', e.target.value)} maxLength={10} placeholder="Opcional" />
                    <CampoError mensaje={errores.patente} />
                </label>
                <label>
                    Marca *
                    <input value={valores.marca} onChange={(e) => actualizar('marca', e.target.value)} maxLength={100} />
                    <CampoError mensaje={errores.marca} />
                </label>
                <label>
                    Modelo *
                    <input value={valores.modelo} onChange={(e) => actualizar('modelo', e.target.value)} maxLength={100} />
                    <CampoError mensaje={errores.modelo} />
                </label>
                <label>
                    Horómetro actual
                    <input type="number" step="0.1" min={horometroMinimo} value={valores.horometro_actual} onChange={(e) => actualizar('horometro_actual', e.target.value)} />
                    <CampoError mensaje={errores.horometro_actual} />
                    {esEdicion && <small>No puede ser menor al horómetro actual registrado.</small>}
                </label>
                <label>
                    Estado
                    <select value={valores.estado} onChange={(e) => actualizar('estado', e.target.value)}>
                        {ESTADOS_EQUIPO.map((e) => <option key={e.value} value={e.value}>{e.label}</option>)}
                    </select>
                    <CampoError mensaje={errores.estado} />
                </label>
            </div>
            {errorGeneral && <p className="error">{errorGeneral}</p>}
            <div className="acciones-formulario">
                <button type="submit" disabled={guardando}>{guardando ? 'Guardando…' : 'Guardar'}</button>
                <button type="button" className="secundario" onClick={alCancelar} disabled={guardando}>Cancelar</button>
            </div>
        </form>
    );
}

/** Lista de equipos con paginación y eliminación. Crear y editar tienen su propia página. */
function Equipos({ token, alSesionInvalida }) {
    const navegar = useNavigate();
    const ubicacion = useLocation();
    const [equipos, setEquipos] = useState([]);
    const [pagina, setPagina] = useState(1);
    const [ultimaPagina, setUltimaPagina] = useState(1);
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState(null);
    const [aviso, setAviso] = useState(ubicacion.state?.aviso ?? null);

    // El aviso llega por el estado de navegación; se limpia para que no reaparezca al recargar.
    useEffect(() => {
        if (ubicacion.state?.aviso) navegar(ubicacion.pathname, { replace: true, state: null });
    }, []);

    function manejarError(e) {
        if (e.estado === 401) alSesionInvalida();
        else setError(e.message);
    }

    async function cargarEquipos(paginaDestino = pagina) {
        setCargando(true);
        setError(null);
        try {
            const respuesta = await api(`/equipos?page=${paginaDestino}`, { token });
            setEquipos(respuesta.data);
            setPagina(respuesta.current_page);
            setUltimaPagina(respuesta.last_page);
        } catch (e) {
            manejarError(e);
        } finally {
            setCargando(false);
        }
    }

    useEffect(() => { cargarEquipos(1); }, [token]);

    async function eliminarEquipo(equipo) {
        if (!window.confirm(`¿Eliminar el equipo "${equipo.codigo}"? Esta acción no se puede deshacer.`)) return;
        setError(null);
        setAviso(null);
        try {
            await api(`/equipos/${equipo.id}`, { token, metodo: 'DELETE' });
            setAviso('Equipo eliminado correctamente.');
            const destino = equipos.length === 1 && pagina > 1 ? pagina - 1 : pagina;
            await cargarEquipos(destino);
        } catch (e) {
            manejarError(e);
        }
    }

    return (
        <section>
            <div className="barra-seccion">
                <div>
                    <h2>Equipos</h2>
                    <p className="resumen">Crear, consultar, editar y eliminar equipos de la flota.</p>
                </div>
                <button type="button" onClick={() => navegar('/equipos/nuevo')}>Nuevo equipo</button>
            </div>

            {aviso && <p className="aviso">{aviso}</p>}
            {error && <p className="error">{error}</p>}

            {cargando ? (
                <p className="resumen">Cargando equipos…</p>
            ) : (
                <div className="tabla-contenedor">
                    <table className="tabla">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Tipo</th>
                                <th>Patente</th>
                                <th>Marca</th>
                                <th>Modelo</th>
                                <th>Horómetro</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {equipos.length === 0 ? (
                                <tr><td colSpan="8" className="celda-vacia">No hay equipos registrados.</td></tr>
                            ) : equipos.map((equipo) => (
                                <tr key={equipo.id}>
                                    <td>{equipo.codigo}</td>
                                    <td>{etiquetaTipo(equipo.tipo)}</td>
                                    <td>{equipo.patente || '—'}</td>
                                    <td>{equipo.marca}</td>
                                    <td>{equipo.modelo}</td>
                                    <td>{formatoNumero.format(equipo.horometro_actual)} h</td>
                                    <td><span className={`estado estado-${equipo.estado}`}>{etiquetaEstado(equipo.estado)}</span></td>
                                    <td className="acciones-tabla">
                                        <button type="button" className="secundario" onClick={() => navegar(`/equipos/${equipo.id}/editar`)}>Editar</button>
                                        <button type="button" className="peligro" onClick={() => eliminarEquipo(equipo)}>Eliminar</button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {ultimaPagina > 1 && (
                <div className="paginacion">
                    <button type="button" className="secundario" disabled={pagina <= 1} onClick={() => cargarEquipos(pagina - 1)}>Anterior</button>
                    <span>Página {pagina} de {ultimaPagina}</span>
                    <button type="button" className="secundario" disabled={pagina >= ultimaPagina} onClick={() => cargarEquipos(pagina + 1)}>Siguiente</button>
                </div>
            )}
        </section>
    );
}

/** Página para crear (/equipos/nuevo) o editar (/equipos/:id/editar) un equipo. */
function PaginaEquipo({ token, alSesionInvalida }) {
    const { id } = useParams();
    const navegar = useNavigate();
    const esEdicion = id !== undefined;
    const [equipo, setEquipo] = useState(null);
    const [cargando, setCargando] = useState(esEdicion);
    const [error, setError] = useState(null);
    const [guardando, setGuardando] = useState(false);

    // En edición se pide el equipo a la API, así la URL funciona al recargar o compartirla.
    useEffect(() => {
        if (!esEdicion) return undefined;

        let activo = true;
        setCargando(true);
        setError(null);

        api(`/equipos/${id}`, { token })
            .then((datos) => { if (activo) setEquipo(datos.data ?? datos); })
            .catch((e) => {
                if (!activo) return;
                if (e.estado === 401) alSesionInvalida();
                else setError(e.estado === 404 ? 'El equipo no existe o fue eliminado.' : e.message);
            })
            .finally(() => { if (activo) setCargando(false); });

        return () => { activo = false; };
    }, [id, token]);

    const inicial = useMemo(() => {
        if (!equipo) return EQUIPO_VACIO;
        return {
            codigo: equipo.codigo ?? '',
            tipo: equipo.tipo ?? '',
            patente: equipo.patente ?? '',
            marca: equipo.marca ?? '',
            modelo: equipo.modelo ?? '',
            horometro_actual: String(equipo.horometro_actual ?? 0),
            estado: equipo.estado ?? 'operativo',
        };
    }, [equipo]);

    async function guardar(cuerpo) {
        setGuardando(true);
        try {
            if (esEdicion) {
                await api(`/equipos/${id}`, { token, metodo: 'PUT', cuerpo });
            } else {
                await api('/equipos', { token, metodo: 'POST', cuerpo });
            }
            navegar('/equipos', {
                state: { aviso: esEdicion ? 'Equipo actualizado correctamente.' : 'Equipo creado correctamente.' },
            });
        } catch (e) {
            if (e.estado === 401) alSesionInvalida();
            throw e; // El formulario muestra los errores por campo.
        } finally {
            setGuardando(false);
        }
    }

    return (
        <section>
            <div className="barra-seccion">
                <button type="button" className="secundario" onClick={() => navegar('/equipos')}>← Volver a equipos</button>
            </div>

            {cargando && <p className="resumen">Cargando equipo…</p>}
            {error && <p className="error">{error}</p>}

            {!cargando && !error && (
                <FormularioEquipo
                    inicial={inicial}
                    esEdicion={esEdicion}
                    horometroMinimo={esEdicion ? Number(equipo?.horometro_actual ?? 0) : 0}
                    guardando={guardando}
                    alGuardar={guardar}
                    alCancelar={() => navegar('/equipos')}
                />
            )}
        </section>
    );
}

function Tarjeta({ titulo, valor, nota }) {
    return (
        <div className="tarjeta">
            <span className="tarjeta-titulo">{titulo}</span>
            <strong className="tarjeta-valor">{valor}</strong>
            {nota && <small>{nota}</small>}
        </div>
    );
}

function Reporte({ token, alSesionInvalida }) {
    const [equipos, setEquipos] = useState([]);
    const [equipoId, setEquipoId] = useState('');
    const [desde, setDesde] = useState(aYmd(HACE_90_DIAS));
    const [hasta, setHasta] = useState(aYmd(HOY));
    const [reporte, setReporte] = useState(null);
    const [error, setError] = useState(null);
    const [cargando, setCargando] = useState(false);

    function manejarError(e) {
        if (e.estado === 401) alSesionInvalida();
        else setError(e.message);
    }

    useEffect(() => {
        async function cargarEquipos() {
            try {
                const todos = [];
                let pag = 1;
                let ultima = 1;
                do {
                    const respuesta = await api(`/equipos?page=${pag}`, { token });
                    todos.push(...respuesta.data);
                    ultima = respuesta.last_page;
                    pag++;
                } while (pag <= ultima);

                setEquipos(todos);
                if (todos.length > 0) setEquipoId((actual) => actual || String(todos[0].id));
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
        <section>
            <div className="barra-seccion">
                <div>
                    <h2>Reporte por equipo</h2>
                    <p className="resumen">Consulta consumo, gastos y rendimiento por rango de fechas.</p>
                </div>
            </div>

            <form className="panel filtros" onSubmit={generar}>
                <label>
                    Equipo
                    <select value={equipoId} onChange={(e) => setEquipoId(e.target.value)} required>
                        <option value="" disabled>Seleccionar…</option>
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
                <div>
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
                </div>
            )}
        </section>
    );
}

/** Estructura autenticada: cabecera con pestañas y las rutas /equipos y /reportes. */
function Aplicacion({ token, alSalir }) {
    const claseTab = ({ isActive }) => (isActive ? 'tab activa' : 'tab');

    return (
        <div className="pagina pagina-amplia">
            <header className="encabezado">
                <div>
                    <h1>Gestión de flota</h1>
                    <nav className="tabs" aria-label="Secciones">
                        <NavLink to="/equipos" className={claseTab}>Equipos</NavLink>
                        <NavLink to="/reportes" className={claseTab}>Reportes</NavLink>
                    </nav>
                </div>
                <button type="button" className="secundario" onClick={alSalir}>Cerrar sesión</button>
            </header>

            <Routes>
                <Route path="/equipos" element={<Equipos token={token} alSesionInvalida={alSalir} />} />
                <Route path="/equipos/nuevo" element={<PaginaEquipo token={token} alSesionInvalida={alSalir} />} />
                <Route path="/equipos/:id/editar" element={<PaginaEquipo token={token} alSesionInvalida={alSalir} />} />
                <Route path="/reportes" element={<Reporte token={token} alSesionInvalida={alSalir} />} />
                <Route path="/login" element={<VolverDesdeLogin />} />
                <Route path="*" element={<Navigate to="/equipos" replace />} />
            </Routes>
        </div>
    );
}

/** Con sesión iniciada, /login no tiene sentido: vuelve a la página que se quería ver (o a /equipos). */
function VolverDesdeLogin() {
    const { state } = useLocation();
    const destino = state?.desde && state.desde !== '/login' ? state.desde : '/equipos';
    return <Navigate to={destino} replace />;
}

/** Sin sesión: solo existe /login. Cualquier otra URL redirige ahí y recuerda a dónde iba el usuario. */
function Acceso({ alIngresar }) {
    const ubicacion = useLocation();

    return (
        <Routes>
            <Route path="/login" element={<Login alIngresar={alIngresar} />} />
            <Route path="*" element={<Navigate to="/login" replace state={{ desde: ubicacion.pathname }} />} />
        </Routes>
    );
}

function App() {
    const [token, setToken] = useState(leerToken);

    function ingresar(nuevoToken) {
        guardarToken(nuevoToken);
        setToken(nuevoToken);
    }

    async function salir() {
        if (token) {
            await api('/logout', { metodo: 'POST', token }).catch(() => {});
        }
        guardarToken(null);
        setToken(null);
    }

    return (
        <BrowserRouter>
            {token
                ? <Aplicacion token={token} alSalir={salir} />
                : <Acceso alIngresar={ingresar} />}
        </BrowserRouter>
    );
}

createRoot(document.getElementById('root')).render(<App />);