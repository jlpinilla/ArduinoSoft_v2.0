/*
 * ========================================================================
 *                           ArduinoSoft v2.0
 * ========================================================================
 * 
 * Proyecto: Sistema de Monitoreo Ambiental Inteligente
 * Plataforma: Arduino MKR WiFi 1010
 * Versión: 2.0
 * Autor: [Nombre del desarrollador]
 * Fecha: Diciembre 2024
 * 
 * DESCRIPCIÓN:
 * Sistema completo de monitoreo ambiental que integra múltiples sensores
 * para la medición y registro de parámetros ambientales en tiempo real.
 * 
 * FUNCIONALIDADES PRINCIPALES:
 * • Monitoreo continuo de temperatura y humedad (DHT22)
 * • Medición de calidad del aire CO2 (MQ135)
 * • Detección de niveles de luz ambiental (BH1750)
 * • Medición calibrada de niveles de ruido
 * • Registro temporal con RTC DS3231
 * • Conectividad WiFi para transmisión de datos
 * • Almacenamiento en base de datos MySQL remota
 * • Interfaz web de configuración con diseño Microsoft Fluent
 * • Sistema de horarios operativos programables
 * • Configuración mediante Access Point WiFi
 * • Almacenamiento persistente en memoria flash
 * 
 * CARACTERÍSTICAS TÉCNICAS:
 * • Optimizado para Arduino MKR WiFi 1010
 * • Protocolo de comunicación I2C para sensores
 * • Servidor web embebido para configuración
 * • Sistema de autenticación seguro
 * • Reconexión automática WiFi y base de datos
 * • Calibración automática de sensores
 * • Manejo de errores y reintentos
 * 
 * SENSORES COMPATIBLES:
 * • DHT22: Temperatura y humedad relativa
 * • MQ135: Calidad del aire (CO2, NH3, NOx)
 * • BH1750: Sensor de luz digital I2C
 * • Micrófono analógico: Medición de ruido calibrada
 * • RTC DS3231: Reloj de tiempo real de alta precisión
 * 
 * CONECTIVIDAD:
 * • WiFi 802.11 b/g/n mediante chip NINA W102
 * • Protocolo HTTP para servidor web
 * • Conexión MySQL directa para almacenamiento
 * • Generación automática de identificadores únicos
 * 
 * USO:
 * 1. Al encender, el dispositivo verifica su configuración
 * 2. Si no está configurado, crea un Access Point "ArduinoSensor"
 * 3. Conectarse a la red y acceder a 192.168.4.1
 * 4. Autenticarse con credenciales admin
 * 5. Configurar WiFi, ubicación, base de datos y horarios
 * 6. El dispositivo se conecta automáticamente y comienza el monitoreo
 * 7. Los datos se envían a la base de datos cada 10 minutos
 * 
 * CONFIGURACIÓN DE PINES:
 * • A0: Sensor MQ135 (analógico)
 * • A1: Sensor de ruido (analógico)
 * • D1: Sensor MQ135 (digital)
 * • D2: Botón de configuración
 * • D5: Sensor DHT22
 * • SDA/SCL: Sensores I2C (BH1750, RTC)
 * 
 * ========================================================================
 */

// ===== INCLUSIÓN DE LIBRERÍAS =====
#include <WiFiNINA.h>         // Para funcionalidad WiFi
#include <WiFiSSLClient.h>    // Para conexiones seguras
#include <Wire.h>             // Para I2C hardware (para RTC y BH1750)
#include <BH1750.h>           // Sensor de luz
#include <DHT.h>              // Sensor temperatura/humedad
#include <RTClib.h>           // Reloj de tiempo real
#include <FlashStorage.h>     // Almacenamiento persistente 
#include <MySQL_Connection.h> // Gestor de conexión MySQL sobre WiFiClient
#include <MySQL_Cursor.h>     // Permite ejecutar consultas SQL
#include <MQUnifiedsensor.h>  // Librería MQ135

// ===== DEFINICIÓN DE PINES =====
#define PIN_MQ135_A A0        // Entrada analógica del sensor MQ135 (A0)
#define PIN_MQ135_D 1         // Entrada digital del sensor MQ135 (D1)
#define PIN_DHT22 5           // Sensor temperatura/humedad
#define PIN_SONIDO A1         // Sensor de sonido
#define PIN_BOTON 2           // Botón de configuración

// ===== CONFIGURACIÓN WIFI HOTSPOT =====
const char* ssidAP = "ArduinoSensor";
const char* passAP = "Abcd1234";


// ===== CREDENCIALES ADMIN =====
const char* usuarioAdmin = "ArduinoAdmin";
const char* passAdmin = "Sensor2025";

// ===== CONSTANTES =====
const int MQTT_PORT = 3307;   // Puerto MySQL estándar
const unsigned long INTERVALO_LECTURA = 10000;      // 10 segundos
const unsigned long INTERVALO_REGISTRO_BD = 600000; // 10 minutos

// ===== CALIBRAR SENSOR DB SONIDO =====
const int lecturaRef  = 789;    // ejemplo: lectura ≈860
const float dBRef     = 48.6;   // corresponde a 48 dB
int offset            = 48.2;      // medir en silencio si no baja de cero
float factor;                   // pendiente de la calibración

// ===== CALIBRAR SENSOR MQ135 =====//

#define placa "MKR WiFi 1010"
#define Voltage_Resolution 5
#define pin A0                  //Pin analogico de arduino
#define type "MQ-135"           //Tipo de sensor
#define ADC_Bit_Resolution 10   
#define RatioMQ135CleanAir 3.6  //RS / R0 = 3.6 ppm  Factor de correccion
MQUnifiedsensor MQ135(placa, Voltage_Resolution, ADC_Bit_Resolution, pin, type);  //Declara el sensor



// ===== INSTANCIAS DE OBJETOS =====
DHT dht(PIN_DHT22, DHT22);
BH1750 lightSensor;
RTC_DS3231 rtc;
WiFiServer servidor(80);
WiFiClient clienteWiFi;
MySQL_Connection conn((Client *)&clienteWiFi);
MySQL_Cursor *cursor = NULL;

// ===== ESTRUCTURA DE CONFIGURACIÓN =====
typedef struct {
    char ssid[33];
    char password[65];
    char ubicacion[100];
    char mac[18];
    char nombre_sensor[25];
    char db_server[100];
    char db_user[50];
    char db_pass[50];
    char db_name[50];
    int hora_inicio;
    int minuto_inicio;
    int hora_fin;
    int minuto_fin;
    bool configurado;
} ConfiguracionSensor;

// ===== ALMACENAMIENTO FLASH =====
FlashStorage(flash_config, ConfiguracionSensor);
ConfiguracionSensor config;

// ===== VARIABLES GLOBALES =====
unsigned long ultimaLectura = 0;
unsigned long ultimoRegistroBD = 0;
bool enHoraOperativa = false;

// ===== DECLARACIÓN DE FUNCIONES =====
bool iniciarAccessPoint();
void handleClient();
void enviarPaginaLogin(WiFiClient& cliente, bool loginFallido);
void enviarPaginaConfig(WiFiClient& cliente);
void procesarFormulario(String postBody);
bool conectarWiFi();
bool conectarBaseDatos();
void registrarDispositivo();
void leerSensores();
void enviarRegistroBD();
bool estaEnHorarioOperativo();
String obtenerDireccionMAC();
String generarNombreSensor(String mac);
void enviarPaginaConfirmacion(WiFiClient& cliente);
String urldecode(String str);
unsigned char hexToDecimal(char c);

void setup() {
    // Inicializar Serial sin esperar conexión
    Serial.begin(115200);
    // Removed: while (!Serial) delay(10); // This line caused the dependency
    Serial.println("\n==== Sensor Ambiental Iniciando ====");
    
    // Inicializar sensores inmediatamente
    Wire.begin();
    dht.begin();
    
    // Inicializar BH1750 con verificación
    if (lightSensor.begin()) {
        Serial.println("BH1750 inicializado correctamente");
    } else {
        Serial.println("Error inicializando BH1750");
    }
    
    // Inicializar RTC
    if (!rtc.begin()) {
        Serial.println("No se pudo encontrar el RTC");
    } else {
        Serial.println("RTC inicializado correctamente");
    }

    // Calibrar sensor de sonido
    factor = (lecturaRef - offset) / dBRef;  
    Serial.print("Factor de calibración sonido: ");
    Serial.println(factor);

    //Calibrar MQ135
    MQ135.setRegressionMethod(1);  //Metodo para calcular PPM =  a*ratio^b
    MQ135.setA(110.47);
    MQ135.setB(-2.862);
    MQ135.init();
    
    float calcR0 = 0;
    for (int i = 1; i <= 10; i++) {
        MQ135.update();  //  Se actualizan la lectura analogica
        calcR0 += MQ135.calibrate(RatioMQ135CleanAir);
        Serial.print(".");
    }
    MQ135.setR0(calcR0 / 10);
    Serial.println("\nMQ135 calibrado correctamente");
    
    // Inicializar entrada/salida
    pinMode(PIN_MQ135_D, INPUT);
    pinMode(PIN_BOTON, INPUT_PULLUP);
    
    // Cargar configuración
    config = flash_config.read();
    
    // Si no está configurado o se presiona el botón, entrar en modo configuración
    if (!config.configurado || digitalRead(PIN_BOTON) == LOW) {
        Serial.println("Iniciando modo configuración (Access Point)");
        
        if (iniciarAccessPoint()) {
            servidor.begin();
            Serial.println(" Servidor web iniciado");
            
            // Esperar hasta que se complete la configuración o timeout
            unsigned long tiempoInicio = millis();
            while (!config.configurado && (millis() - tiempoInicio < 300000)) { // 5 minutos timeout
                handleClient();
                delay(10);
            }
        }
    }
    
    if (config.configurado) {
        Serial.println("Dispositivo configurado, intentando conectar a WiFi");
        
        // Intentar conectarse a WiFi
        for (int intento = 1; intento <= 3; intento++) {
            Serial.print("Intento ");
            Serial.print(intento);
            Serial.print("/3: Conectando a ");
            Serial.println(config.ssid);
            
            if (conectarWiFi()) {
                Serial.print("Conectado correctamente a la wifi en la dirección ip ");
                Serial.println(WiFi.localIP());
                
                // Intentar conectar a la base de datos
                for (int intentoDB = 1; intentoDB <= 5; intentoDB++) {
                    Serial.print("Intento ");
                    Serial.print(intentoDB);
                    Serial.print("/5: Conectando a MySQL (");
                    Serial.print(config.db_server);
                    Serial.print(", ");
                    Serial.print(config.db_user);
                    Serial.print(", ");
                    Serial.print("******, "); // No mostrar contraseña
                    Serial.print(config.db_name);
                    Serial.println(")");
                    
                    if (conectarBaseDatos()) {
                        Serial.println("Conexión con la base de datos exitosa");
                        registrarDispositivo();
                        
                        // Iniciar la primera lectura de sensores inmediatamente después de registrar
                        Serial.println("Iniciando monitoreo de sensores...");
                        leerSensores();
                        ultimaLectura = millis();
                        
                        // Realizar el primer envío a la base de datos
                        Serial.println("Enviando primer registro a la base de datos...");
                        enviarRegistroBD();
                        ultimoRegistroBD = millis();
                        
                        break;
                    }
                    
                    if (intentoDB == 5) {
                        Serial.println("Error conectando a la base de datos después de 5 intentos");
                    } else {
                        delay(3000);
                    }
                }
                break;
            }
            
            if (intento == 3) {
                Serial.println("Error conectando a la wifi después de 3 intentos");
            } else {
                delay(5000);
            }
        }
    } else {
        Serial.println("El dispositivo no ha sido configurado aún");
    }
}

void loop() {
    // Removed duplicate Serial.begin() - this should only be in setup()
    unsigned long tiempoActual = millis();
    
    if (config.configurado) {
        enHoraOperativa = estaEnHorarioOperativo();
        
        if (enHoraOperativa) {
            // Leer datos cada 10 segundos
            if (tiempoActual - ultimaLectura >= INTERVALO_LECTURA) {
                leerSensores();
                ultimaLectura = tiempoActual;
            }
            
            // Enviar datos a la base de datos cada 10 minutos
            if (tiempoActual - ultimoRegistroBD >= INTERVALO_REGISTRO_BD) {
                enviarRegistroBD();
                ultimoRegistroBD = tiempoActual;
            }
        }
    } else {
        // Si no está configurado y en loop, activar modo AP para configuración
        // 2024-12-19: Corregido operador lógico - cambiado != por comparación correcta
        if (WiFi.status() != WL_AP_LISTENING) {
            iniciarAccessPoint();
            servidor.begin();
        }
        handleClient();
    }
}

bool iniciarAccessPoint() {
    Serial.println("Iniciando Access Point...");
    WiFi.end();
    delay(1000);
    
    int intentos = 0;
    while (intentos < 3) {
        if (WiFi.beginAP(ssidAP, passAP) == WL_AP_LISTENING) {
            delay(5000); // Esperar a que se inicialice el AP
            Serial.println("Access Point iniciado correctamente");
            Serial.print("SSID: ");
            Serial.println(ssidAP);
            Serial.print("IP: 192.168.4.1");
            return true;
        }
        
        Serial.println("Fallo al iniciar AP, reintentando...");
        delay(2000);
        intentos++;
    }
    
    Serial.println("Error crítico al iniciar el modo AP");
    return false;
}

void handleClient() {
    WiFiClient cliente = servidor.available();
    if (cliente) {
        Serial.println("Nuevo cliente conectado");
        String currentLine = "";
        String requestHeader = "";
        bool currentLineIsBlank = true;
        unsigned long currentTime = millis();
        unsigned long previousTime = currentTime;
        const unsigned long timeoutTime = 2000;
        
        // Primero, leer todos los encabezados HTTP
        while (cliente.connected() && currentTime - previousTime <= timeoutTime) {
            currentTime = millis();
            if (cliente.available()) {
                char c = cliente.read();
                requestHeader += c;
                
                if (c == '\n') {
                    // Recibimos una línea en blanco después de los encabezados
                    if (currentLineIsBlank) {
                        // Fin de los encabezados HTTP
                        Serial.println("Encabezados recibidos:");
                        Serial.println(requestHeader);
                        
                        // Extraer Content-Length para cualquier petición POST
                        String contentLengthStr = "";
                        int contentLength = 0;
                        
                        if (requestHeader.indexOf("POST") >= 0) {
                            // Buscar Content-Length en los encabezados para todas las peticiones POST
                            int contentLengthPos = requestHeader.indexOf("Content-Length: ");
                            if (contentLengthPos > 0) {
                                contentLengthStr = requestHeader.substring(contentLengthPos + 16);
                                contentLengthStr = contentLengthStr.substring(0, contentLengthStr.indexOf('\r'));
                                contentLength = contentLengthStr.toInt();
                                Serial.print("Content-Length: ");
                                Serial.println(contentLength);
                            }
                            

                            // Esperar a que haya datos disponibles (con timeout)
                            while(cliente.available() < contentLength && currentTime - previousTime <= timeoutTime) {
                                delay(10);
                                currentTime = millis();
                            }
                            
                            // Leer el cuerpo POST
                            String postBody = "";
                            for (int i = 0; i < contentLength && cliente.available(); i++) {
                                postBody += (char)cliente.read();
                            }
                            
                            Serial.println("Cuerpo POST: " + postBody);
                            
                            // Determinar el tipo de petición POST
                            if (requestHeader.indexOf("POST /login") >= 0) {
                                // Procesar login
                                int usernamePos = postBody.indexOf("username=");
                                int passwordPos = postBody.indexOf("password=");
                                

                                if (usernamePos >= 0 && passwordPos >= 0) {
                                    int nextAmpPos = postBody.indexOf("&", usernamePos);
                                    String username = postBody.substring(usernamePos + 9, nextAmpPos);
                                    String password = postBody.substring(passwordPos + 9);
                                    
                                    // Decodificar URL encoding
                                    username.replace("+", " ");
                                    password.replace("+", " ");
                                    username.trim();
                                    password.trim();
                                    
                                    if (username.equals(usuarioAdmin) && password.equals(passAdmin)) {
                                        enviarPaginaConfig(cliente);
                                    } else {
                                        enviarPaginaLogin(cliente, true);
                                    }
                                } else {
                                    enviarPaginaLogin(cliente, false);
                                }
                            } else if (requestHeader.indexOf("POST /config") >= 0) {
                                // Procesar formulario de configuración con el cuerpo ya leído
                                procesarFormulario(postBody);
                                enviarPaginaConfirmacion(cliente);
                            }
                        } else {
                            // Peticiones GET o iniciales
                            enviarPaginaLogin(cliente, false);
                        }
                        break;
                    }
                    currentLineIsBlank = true;
                    currentLine = "";
                } else if (c != '\r') {
                    currentLineIsBlank = false;
                    currentLine += c;
                }
            }
        }
        
        // Dar tiempo para que se envíen los datos
        delay(10);
        cliente.stop();
        Serial.println("Cliente desconectado");
    }
}

void enviarPaginaLogin(WiFiClient& cliente, bool loginFallido) {
    Serial.println("Enviando página de login");
    
    // Enviar encabezados HTTP
    cliente.println("HTTP/1.1 200 OK");
    cliente.println("Content-Type: text/html");
    cliente.println("Connection: close");
    cliente.println();
    
    // Inicio documento HTML
    cliente.println("<!DOCTYPE HTML>");
    cliente.println("<html>");
    cliente.println("<head>");
    cliente.println("<meta charset='UTF-8'>");
    cliente.println("<meta name='viewport' content='width=device-width, initial-scale=1'>");
    cliente.println("<title>Login Sensor Ambiental</title>");
    
    // Estilos CSS
    cliente.println("<style>");
    cliente.println("body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 24px; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); color: #323130; min-height: 100vh; }");
    cliente.println("h1 { color: #0078d4; text-align: center; font-weight: 600; font-size: 28px; margin-bottom: 32px; }");
    cliente.println("form { max-width: 400px; margin: 0 auto; background: rgba(255, 255, 255, 0.95); padding: 32px; border-radius: 12px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2); }");
    cliente.println(".form-group { margin-bottom: 20px; }");
    cliente.println("label { display: block; margin-bottom: 8px; color: #323130; font-weight: 600; font-size: 14px; }");
    cliente.println("input[type='text'], input[type='password'] { width: 100%; padding: 12px 16px; border: 2px solid #e1dfdd; border-radius: 8px; box-sizing: border-box; font-size: 16px; transition: all 0.2s ease; background: #ffffff; }");
    cliente.println("input[type='text']:focus, input[type='password']:focus { outline: none; border-color: #0078d4; box-shadow: 0 0 0 1px #0078d4; }");
    cliente.println("input[type='submit'] { background: linear-gradient(135deg, #0078d4 0%, #106ebe 100%); color: white; border: none; padding: 14px 24px; border-radius: 8px; cursor: pointer; width: 100%; font-size: 16px; font-weight: 600; transition: all 0.2s ease; box-shadow: 0 2px 8px rgba(0, 120, 212, 0.3); }");
    cliente.println("input[type='submit']:hover { background: linear-gradient(135deg, #106ebe 0%, #005a9e 100%); transform: translateY(-1px); box-shadow: 0 4px 16px rgba(0, 120, 212, 0.4); }");
    cliente.println("input[type='submit']:active { transform: translateY(0); box-shadow: 0 2px 8px rgba(0, 120, 212, 0.3); }");
    cliente.println(".error { color: #d13438; margin-bottom: 20px; text-align: center; font-weight: 600; padding: 12px; background: rgba(209, 52, 56, 0.1); border-radius: 8px; border-left: 4px solid #d13438; }");
    cliente.println("</style>");
    cliente.println("</head>");
    
    // Cuerpo del documento
    cliente.println("<body>");
    cliente.println("<h1>Sensor Ambiental</h1>");
    cliente.println("<form method='post' action='/login'>");
    
    // Mensaje de error si el login falló
    if (loginFallido) {
        cliente.println("<div class='error'>Usuario o contraseña incorrectos</div>");
    }
    
    // Campos del formulario
    cliente.println("<div class='form-group'>");
    cliente.println("<label for='username'>Usuario:</label>");
    cliente.println("<input type='text' id='username' name='username' required>");
    cliente.println("</div>");
    
    cliente.println("<div class='form-group'>");
    cliente.println("<label for='password'>Contraseña:</label>");
    cliente.println("<input type='password' id='password' name='password' required>");
    cliente.println("</div>");
    
    // Botón de envío
    cliente.println("<div class='form-group'>");
    cliente.println("<input type='submit' value='Iniciar Sesión'>");
    cliente.println("</div>");
    
    // Cierre del documento
    cliente.println("</form>");
    cliente.println("</body>");
    cliente.println("</html>");
    
    Serial.println("Página login enviada completamente");
}

void enviarPaginaConfig(WiFiClient& cliente) {
    String mac = obtenerDireccionMAC();
    String nombreSensor = generarNombreSensor(mac);
    DateTime ahora = rtc.now();
    
    // Crear string para el valor datetime-local
    char fechaHora[20];
    sprintf(fechaHora, "%04d-%02d-%02dT%02d:%02d", 
            ahora.year(), ahora.month(), ahora.day(), 
            ahora.hour(), ahora.minute());
    
    Serial.println("Enviando página de configuración");
    
    // Enviar encabezados HTTP
    cliente.println("HTTP/1.1 200 OK");
    cliente.println("Content-Type: text/html");
    cliente.println("Connection: close");
    cliente.println();
    
    // Inicio documento HTML
    cliente.println("<!DOCTYPE HTML>");
    cliente.println("<html>");
    cliente.println("<head>");
    cliente.println("<meta charset='UTF-8'>");
    cliente.println("<meta name='viewport' content='width=device-width, initial-scale=1'>");
    cliente.println("<title>Configuración Sensor Ambiental</title>");
    
    // Estilos CSS
    cliente.println("<style>");
    cliente.println("body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 24px; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); color: #323130; min-height: 100vh; }");
    cliente.println("h1 { color: #0078d4; text-align: center; font-weight: 600; font-size: 28px; margin-bottom: 32px; }");
    cliente.println("form { max-width: 600px; margin: 0 auto; background: rgba(255, 255, 255, 0.95); padding: 32px; border-radius: 12px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2); }");
    cliente.println(".form-group { margin-bottom: 20px; }");
    cliente.println("label { display: block; margin-bottom: 8px; color: #323130; font-weight: 600; font-size: 14px; }");
    cliente.println("input[type='text'], input[type='password'], input[type='number'], input[type='time'], input[type='datetime-local'], input[type='file'] { width: 100%; padding: 12px 16px; border: 2px solid #e1dfdd; border-radius: 8px; box-sizing: border-box; font-size: 16px; transition: all 0.2s ease; background: #ffffff; }");
    cliente.println("input[type='text']:focus, input[type='password']:focus, input[type='number']:focus, input[type='time']:focus, input[type='datetime-local']:focus { outline: none; border-color: #0078d4; box-shadow: 0 0 0 1px #0078d4; }");
    cliente.println("input[type='submit'], button { background: linear-gradient(135deg, #0078d4 0%, #106ebe 100%); color: white; border: none; padding: 14px 24px; border-radius: 8px; cursor: pointer; width: 100%; font-size: 16px; font-weight: 600; margin-bottom: 12px; transition: all 0.2s ease; box-shadow: 0 2px 8px rgba(0, 120, 212, 0.3); }");
    cliente.println("input[type='submit']:hover, button:hover { background: linear-gradient(135deg, #106ebe 0%, #005a9e 100%); transform: translateY(-1px); box-shadow: 0 4px 16px rgba(0, 120, 212, 0.4); }");
    cliente.println("input[type='submit']:active, button:active { transform: translateY(0); box-shadow: 0 2px 8px rgba(0, 120, 212, 0.3); }");
    cliente.println("input[readonly] { background-color: #f8f9fa; border-color: #e1dfdd; color: #605e5c; }");
    cliente.println(".note { font-size: 12px; color: #605e5c; margin-top: 8px; font-style: italic; line-height: 1.4; }");
    cliente.println(".file-section { margin-bottom: 24px; padding: 24px; background: rgba(0, 120, 212, 0.05); border-radius: 12px; border: 1px solid rgba(0, 120, 212, 0.1); }");
    cliente.println(".file-section h3 { margin-top: 0; color: #0078d4; font-weight: 600; font-size: 18px; }");
    cliente.println(".template-box { background: #f8f9fa; padding: 16px; border-radius: 8px; font-family: 'Consolas', 'Monaco', monospace; white-space: pre-wrap; border-left: 4px solid #0078d4; font-size: 12px; overflow-x: auto; }");
    cliente.println(".button-group { display: flex; gap: 12px; margin-bottom: 16px; }");
    cliente.println(".button-group button { flex: 1; }");
    cliente.println("h4 { color: #323130; font-weight: 600; margin-top: 24px; margin-bottom: 12px; }");
    cliente.println("p { line-height: 1.5; margin-bottom: 16px; }");
    cliente.println("</style>");
    
    // JavaScript para procesar el archivo de configuración y descargar plantilla
    cliente.println("<script>");
    cliente.println("function processConfigFile(event) {");
    cliente.println("  const file = event.target.files[0];");
    cliente.println("  if (file) {");
    cliente.println("    const reader = new FileReader();");
    cliente.println("    reader.onload = function(e) {");
    cliente.println("      const content = e.target.result;");
    cliente.println("      const lines = content.split('\\n');");
    cliente.println("      const config = {};");

    cliente.println("      lines.forEach(line => {");
    cliente.println("        if (line.trim() && !line.startsWith('#')) {");
    cliente.println("          const [key, value] = line.split('=');");
    cliente.println("          if (key && value) {");
    cliente.println("            config[key.trim()] = value.trim();");
    cliente.println("          }");
    cliente.println("        }");
    cliente.println("      });");
    cliente.println("      ");
    cliente.println("      // Rellenar el formulario con los valores del archivo");
    cliente.println("      if (config.ssid) document.getElementById('ssid').value = config.ssid;");
    cliente.println("      if (config.password) document.getElementById('password').value = config.password;");
    cliente.println("      if (config.ubicacion) document.getElementById('ubicacion').value = config.ubicacion;");
    cliente.println("      if (config.db_server) document.getElementById('db_server').value = config.db_server;");
    cliente.println("      if (config.db_user) document.getElementById('db_user').value = config.db_user;");
    cliente.println("      if (config.db_pass) document.getElementById('db_pass').value = config.db_pass;");
    cliente.println("      if (config.db_name) document.getElementById('db_name').value = config.db_name;");
    cliente.println("      if (config.hora_inicio) document.getElementById('hora_inicio').value = config.hora_inicio;");
    cliente.println("      if (config.hora_fin) document.getElementById('hora_fin').value = config.hora_fin;");
    cliente.println("      if (config.rtc_datetime) document.getElementById('rtc_datetime').value = config.rtc_datetime;");
    cliente.println("      alert('Archivo de configuración cargado correctamente');");
    cliente.println("    };");
    cliente.println("    reader.readAsText(file);");
    cliente.println("  }");
    cliente.println("}");
    
    // Función para descargar la plantilla
    cliente.println("function downloadTemplate() {");
    cliente.println("  const template = `# Archivo de configuración para Sensor Ambiental");
    cliente.println("# Formato: clave=valor (sin espacios alrededor del =)");
    cliente.println("");
    cliente.println("# Configuración WiFi");
    cliente.println("ssid=NombreDeRed");
    cliente.println("password=ContraseñaWiFi");
    cliente.println("");
    cliente.println("# Información del sensor");
    cliente.println("ubicacion=Sala principal");
    cliente.println("");
    cliente.println("# Configuración base de datos MySQL");
    cliente.println("db_server=192.168.1.100");
    cliente.println("db_user=usuario_mysql");
    cliente.println("db_pass=contraseña_mysql");
    cliente.println("db_name=sensores_db");
    cliente.println("");
    cliente.println("# Horario de operación (formato 24h: HH:MM)");
    cliente.println("hora_inicio=08:00");
    cliente.println("hora_fin=20:00");
    cliente.println("");
    cliente.println("# Ajuste de fecha y hora (formato: YYYY-MM-DDThh:mm)");
    cliente.println("rtc_datetime=" + String(fechaHora) + "`");
    cliente.println("");
    cliente.println("  const blob = new Blob([template], {type: 'text/plain'});");
    cliente.println("  const url = URL.createObjectURL(blob);");
    cliente.println("  const a = document.createElement('a');");
    cliente.println("  a.href = url;");
    cliente.println("  a.download = 'registro.cfg';");
    cliente.println("  document.body.appendChild(a);");
    cliente.println("  a.click();");
    cliente.println("  document.body.removeChild(a);");
    cliente.println("  URL.revokeObjectURL(url);");
    cliente.println("}");
    cliente.println("</script>");
    cliente.println("</head>");
    
    // Cuerpo del documento
    cliente.println("<body>");
    cliente.println("<h1>Configuración Sensor Ambiental</h1>");
    
    // Sección para cargar archivo de configuración
    cliente.println("<div class='file-section'>");
    cliente.println("<h3>Cargar archivo de configuración</h3>");
    cliente.println("<p>Puede cargar un archivo registro.cfg con la configuración predefinida:</p>");
    
    // Agregar un div con dos botones: uno para cargar y otro para descargar la plantilla
    cliente.println("<div class='button-group'>");
    cliente.println("<input type='file' id='config-file' accept='.cfg' onchange='processConfigFile(event)'>");
    cliente.println("<button type='button' onclick='downloadTemplate()'>Descargar plantilla registro.cfg</button>");
    cliente.println("</div>");
    
    cliente.println("<p class='note'>El archivo debe tener el formato correcto (ver ejemplo abajo)</p>");
    
    cliente.println("<h4>Plantilla del archivo registro.cfg:</h4>");
    cliente.println("<div class='template-box'>");
    cliente.println("# Archivo de configuración para Sensor Ambiental");
    cliente.println("# Formato: clave=valor (sin espacios alrededor del =)");
    cliente.println("");
    cliente.println("# Configuración WiFi");
    cliente.println("ssid=NombreDeRed");
    cliente.println("password=ContraseñaWiFi");
    cliente.println("");
    cliente.println("# Información del sensor");
    cliente.println("ubicacion=Sala principal");
    cliente.println("");
    cliente.println("# Configuración base de datos MySQL");
    cliente.println("db_server=192.168.1.100");
    cliente.println("db_user=usuario_mysql");
    cliente.println("db_pass=contraseña_mysql");
    cliente.println("db_name=sensores_db");
    cliente.println("");
    cliente.println("# Horario de operación (formato 24h: HH:MM)");
    cliente.println("hora_inicio=08:00");
    cliente.println("hora_fin=20:00");
    cliente.println("");
    cliente.println("# Ajuste de fecha y hora (formato: YYYY-MM-DDThh:mm)");
    cliente.println("rtc_datetime=" + String(fechaHora));
    cliente.println("</div>");
    cliente.println("</div>");
    
    // Formulario principal
    cliente.println("<form method='post' action='/config'>");
    
    // Sección: WiFi
    cliente.println("<div class='form-group'>");
    cliente.println("<label for='ssid'>SSID WiFi:</label>");
    cliente.println("<input type='text' id='ssid' name='ssid' required>");
    cliente.println("</div>");
    
    cliente.println("<div class='form-group'>");
    cliente.println("<label for='password'>Contraseña WiFi:</label>");
    cliente.println("<input type='password' id='password' name='password'>");
    cliente.println("</div>");
    
    // Sección: Información del sensor
    cliente.println("<div class='form-group'>");
    cliente.println("<label for='ubicacion'>Ubicación del sensor:</label>");
    cliente.println("<input type='text' id='ubicacion' name='ubicacion' required>");
    cliente.println("</div>");
    
    cliente.println("<div class='form-group'>");
    cliente.println("<label for='mac'>Dirección MAC:</label>");
    cliente.println("<input type='text' id='mac' name='mac' value='" + mac + "' readonly>");
    cliente.println("<p class='note'>Generado automáticamente</p>");
    cliente.println("</div>");
    
    cliente.println("<div class='form-group'>");
    cliente.println("<label for='nombre'>Nombre del sensor:</label>");
    cliente.println("<input type='text' id='nombre' name='nombre' value='" + nombreSensor + "' readonly>");
    cliente.println("<p class='note'>Generado automáticamente</p>");
    cliente.println("</div>");
    
    // Sección: Base de datos
    cliente.println("<div class='form-group'>");
    cliente.println("<label for='db_server'>Dirección IP del servidor MySQL:</label>");
    cliente.println("<input type='text' id='db_server' name='db_server' required>");
    cliente.println("</div>");
    
    cliente.println("<div class='form-group'>");
    cliente.println("<label for='db_user'>Usuario de la base de datos:</label>");
    cliente.println("<input type='text' id='db_user' name='db_user' required>");
    cliente.println("</div>");
    
    cliente.println("<div class='form-group'>");
    cliente.println("<label for='db_pass'>Contraseña de la base de datos:</label>");
    cliente.println("<input type='password' id='db_pass' name='db_pass' required>");
    cliente.println("</div>");
    
    cliente.println("<div class='form-group'>");
    cliente.println("<label for='db_name'>Nombre de la base de datos:</label>");
    cliente.println("<input type='text' id='db_name' name='db_name' required>");
    cliente.println("</div>");
    
    // Sección: Horarios de operación
    cliente.println("<div class='form-group'>");
    cliente.println("<label for='hora_inicio'>Hora de inicio de escaneo:</label>");
    cliente.println("<input type='time' id='hora_inicio' name='hora_inicio' required>");
    cliente.println("</div>");
    
    cliente.println("<div class='form-group'>");
    cliente.println("<label for='hora_fin'>Hora de fin de escaneo:</label>");
    cliente.println("<input type='time' id='hora_fin' name='hora_fin' required>");
    cliente.println("</div>");
    
    // Ajuste de fecha y hora
    cliente.println("<div class='form-group'>");
    cliente.println("<label for='rtc_datetime'>Fecha y hora actual:</label>");
    cliente.println("<input type='datetime-local' id='rtc_datetime' name='rtc_datetime' value='" + String(fechaHora) + "' required>");
    cliente.println("<p class='note'>Fecha y hora actual del dispositivo. Actualizar solo si es necesario.</p>");
    cliente.println("</div>");
    
    // Botón de envío
    cliente.println("<div class='form-group'>");
    cliente.println("<input type='submit' value='Guardar Configuración'>");
    cliente.println("</div>");
    
    // Cierre del documento
    cliente.println("</form>");
    cliente.println("</body>");
    cliente.println("</html>");
}

void procesarFormulario(String postBody) {
    Serial.println("Procesando formulario de configuración");
    Serial.println("Datos recibidos: " + postBody);
    
    // Extraer datos del formulario del cuerpo POST
    int pos = 0;
    String valor = "";
    
    // SSID WiFi
    pos = postBody.indexOf("ssid=");
    if (pos >= 0) {
        int fin = postBody.indexOf("&", pos);
        if (fin < 0) fin = postBody.length();
        valor = postBody.substring(pos + 5, fin);
        valor.replace("+", " ");
        // Decodificar URL encoding
        valor = urldecode(valor);
        valor.trim();
        Serial.print("SSID WiFi: ");
        Serial.println(valor);
        strncpy(config.ssid, valor.c_str(), sizeof(config.ssid) - 1);
        config.ssid[sizeof(config.ssid) - 1] = '\0';
    }
    
    // Password WiFi
    pos = postBody.indexOf("password=");
    if (pos >= 0) {
        int fin = postBody.indexOf("&", pos);
        if (fin < 0) fin = postBody.length();
        valor = postBody.substring(pos + 9, fin);
        valor.replace("+", " ");
        // Decodificar URL encoding
        valor = urldecode(valor);
        valor.trim();
        Serial.print("Password WiFi: ");
        Serial.println("********");
        strncpy(config.password, valor.c_str(), sizeof(config.password) - 1);
        config.password[sizeof(config.password) - 1] = '\0';
    }
    
    // Ubicación
    pos = postBody.indexOf("ubicacion=");
    if (pos >= 0) {
        int fin = postBody.indexOf("&", pos);
        if (fin < 0) fin = postBody.length();
        valor = postBody.substring(pos + 10, fin);
        valor.replace("+", " ");
        // Decodificar URL encoding
        valor = urldecode(valor);
        valor.trim();
        Serial.print("Ubicación: ");
        Serial.println(valor);
        strncpy(config.ubicacion, valor.c_str(), sizeof(config.ubicacion) - 1);
        config.ubicacion[sizeof(config.ubicacion) - 1] = '\0';
    }
    
    // MAC (ya se ha obtenido automáticamente)
    String mac = obtenerDireccionMAC();
    strncpy(config.mac, mac.c_str(), sizeof(config.mac) - 1);
    config.mac[sizeof(config.mac) - 1] = '\0';
    
    // Nombre del sensor (ya se ha generado automáticamente)
    String nombreSensor = generarNombreSensor(mac);
    strncpy(config.nombre_sensor, nombreSensor.c_str(), sizeof(config.nombre_sensor) - 1);
    config.nombre_sensor[sizeof(config.nombre_sensor) - 1] = '\0';
    
    // Servidor BD
    pos = postBody.indexOf("db_server=");
    if (pos >= 0) {
        int fin = postBody.indexOf("&", pos);
        if (fin < 0) fin = postBody.length();
        valor = postBody.substring(pos + 10, fin);
        valor.replace("+", " ");
        // Decodificar URL encoding
        valor = urldecode(valor);
        valor.trim();
        Serial.print("DB Server: ");
        Serial.println(valor);
        strncpy(config.db_server, valor.c_str(), sizeof(config.db_server) - 1);
        config.db_server[sizeof(config.db_server) - 1] = '\0';
    }
    
    // Usuario BD
    pos = postBody.indexOf("db_user=");
    if (pos >= 0) {
        int fin = postBody.indexOf("&", pos);
        if (fin < 0) fin = postBody.length();
        valor = postBody.substring(pos + 8, fin);
        valor.replace("+", " ");
        // Decodificar URL encoding
        valor = urldecode(valor);
        valor.trim();
        Serial.print("DB User: ");
        Serial.println(valor);
        strncpy(config.db_user, valor.c_str(), sizeof(config.db_user) - 1);
        config.db_user[sizeof(config.db_user) - 1] = '\0';
    }
    
    // Password BD
    pos = postBody.indexOf("db_pass=");
    if (pos >= 0) {
        int fin = postBody.indexOf("&", pos);
        if (fin < 0) fin = postBody.length();
        valor = postBody.substring(pos + 8, fin);
        valor.replace("+", " ");
        // Decodificar URL encoding
        valor = urldecode(valor);
        valor.trim();
        Serial.print("DB Pass: ");
        Serial.println("********");
        strncpy(config.db_pass, valor.c_str(), sizeof(config.db_pass) - 1);
        config.db_pass[sizeof(config.db_pass) - 1] = '\0';
    }
    
    // Nombre BD
    pos = postBody.indexOf("db_name=");
    if (pos >= 0) {
        int fin = postBody.indexOf("&", pos);
        if (fin < 0) fin = postBody.length();
        valor = postBody.substring(pos + 8, fin);
        valor.replace("+", " ");
        // Decodificar URL encoding
        valor = urldecode(valor);
        valor.trim();
        Serial.print("DB Name: ");
        Serial.println(valor);
        strncpy(config.db_name, valor.c_str(), sizeof(config.db_name) - 1);
        config.db_name[sizeof(config.db_name) - 1] = '\0';
    }
    
    // Hora inicio
    pos = postBody.indexOf("hora_inicio=");
    if (pos >= 0) {
        int fin = postBody.indexOf("&", pos);
        if (fin < 0) fin = postBody.length();
        valor = postBody.substring(pos + 12, fin);
        Serial.print("Hora Inicio: ");
        Serial.println(valor);
        if (valor.length() >= 5) {
            String horaStr = valor.substring(0, 2);
            String minStr = valor.substring(3, 5);
            config.hora_inicio = horaStr.toInt();
            config.minuto_inicio = minStr.toInt();
            Serial.print("Hora inicio parseada: ");
            Serial.print(config.hora_inicio);
            Serial.print(":");
            Serial.println(config.minuto_inicio);
        }
    }
    
    // Hora fin
    pos = postBody.indexOf("hora_fin=");
    if (pos >= 0) {
        int fin = postBody.indexOf("&", pos);
        if (fin < 0) fin = postBody.length();
        valor = postBody.substring(pos + 9, fin);
        Serial.print("Hora Fin: ");
        Serial.println(valor);
        if (valor.length() >= 5) {
            String horaStr = valor.substring(0, 2);
            String minStr = valor.substring(3, 5);
            config.hora_fin = horaStr.toInt();
            config.minuto_fin = minStr.toInt();
            Serial.print("Hora fin parseada: ");
            Serial.print(config.hora_fin);
            Serial.print(":");
            Serial.println(config.minuto_fin);
        }
    }
    
    // Fecha y hora RTC
    pos = postBody.indexOf("rtc_datetime=");
    if (pos >= 0) {
        int fin = postBody.indexOf("&", pos);
        if (fin < 0) fin = postBody.length();
        valor = postBody.substring(pos + 13, fin);
        // Decodificar URL encoding
        valor = urldecode(valor);
        valor.trim();
        Serial.print("RTC Datetime (decoded): ");
        Serial.println(valor);
        
        if (valor.length() > 0) {
            // Formato esperado: YYYY-MM-DDTHH:MM o YYYY-MM-DD HH:MM
            int anio = valor.substring(0, 4).toInt();
            int mes = valor.substring(5, 7).toInt();
            int dia = valor.substring(8, 10).toInt();
            int hora = 0;
            int minuto = 0;
            int posT = valor.indexOf('T');
            if (posT < 0) posT = valor.indexOf(' '); // por si acaso viene con espacio
            
            if (posT > 0 && valor.length() >= posT + 6) {
                hora = valor.substring(posT + 1, posT + 3).toInt();
                minuto = valor.substring(posT + 4, posT + 6).toInt();
            }
            
            Serial.print("Fecha a configurar: ");
            Serial.print(anio); Serial.print("-");
            Serial.print(mes); Serial.print("-");
            Serial.print(dia); Serial.print(" ");
            Serial.print(hora); Serial.print(":");
            Serial.println(minuto);
            
            if (anio >= 2000 && mes >= 1 && mes <= 12 && dia >= 1 && dia <= 31) {
                rtc.begin(); // Asegurar que el RTC está inicializado
                rtc.adjust(DateTime(anio, mes, dia, hora, minuto, 0));
                Serial.println("Fecha y hora ajustadas en el RTC");
                // Verificar si se ajustó correctamente
                delay(100);
                DateTime ahora = rtc.now();
                Serial.print("RTC ahora: ");
                Serial.print(ahora.year()); Serial.print("-");
                Serial.print(ahora.month()); Serial.print("-");
                Serial.print(ahora.day()); Serial.print(" ");
                Serial.print(ahora.hour()); Serial.print(":");
                Serial.println(ahora.minute());
            } else {
                Serial.println("Error: Fecha inválida");
            }
        }
    }
    
    // Marcar como configurado
    config.configurado = true;
    
    // Guardar en Flash
    flash_config.write(config);
    Serial.println("Configuración guardada");
}

// Función auxiliar para decodificar URL
String urldecode(String str) {
    String result = "";
    char c;
    char code0;
    char code1;
    for (int i = 0; i < str.length(); i++) {
        c = str.charAt(i);
        if (c == '+') {
            result += ' ';
        } else if (c == '%' && i + 2 < str.length()) {
            code0 = str.charAt(i+1);
            code1 = str.charAt(i+2);
            if (isHexadecimalDigit(code0) && isHexadecimalDigit(code1)) {
                char decoded = (hexToDecimal(code0) << 4) | hexToDecimal(code1);
                result += decoded;
                i += 2;
            } else {
                result += c;
            }
        } else {
            result += c;
        }
    }
    return result;
}

// Función auxiliar para convertir un caracter hexadecimal a decimal
unsigned char hexToDecimal(char c) {
    if (c >= '0' && c <= '9') return c - '0';
    if (c >= 'A' && c <= 'F') return 10 + c - 'A';
    if (c >= 'a' && c <= 'f') return 10 + c - 'a';
    return 0;
}

bool conectarWiFi() {
    WiFi.disconnect();
    WiFi.end();
    delay(1000);
    
    // Iniciar WiFi en modo cliente
    WiFi.begin(config.ssid, config.password);
    
    // Esperar a que se conecte (máximo 10 segundos)
    unsigned long startMills = millis();
    while (WiFi.status() != WL_CONNECTED && millis() - startMills < 10000) {
        delay(500);
        Serial.print(".");
    }
    
    return (WiFi.status() == WL_CONNECTED);
}

bool conectarBaseDatos() {
    IPAddress server_addr;
    if (!server_addr.fromString(config.db_server)) {
        Serial.println("IP del servidor MySQL inválida");
        return false;
    }
    
    // Intentar conectar a MySQL
    if (conn.connect(server_addr, MQTT_PORT, config.db_user, config.db_pass)) {
        Serial.println("Base de datos conectada correctamente");
        cursor = new MySQL_Cursor(&conn);
        
        // Seleccionar la base de datos
        char selectDB[50];
        sprintf(selectDB, "USE %s", config.db_name);
        if (cursor->execute(selectDB)) {
            Serial.println("Base de datos seleccionada correctamente");
            return true;
        } else {
            Serial.print("Error seleccionando la base de datos ");
            Serial.println(config.db_name);
            conn.close();
            return false;
        }
    } else {
        Serial.println("¡Error conectando a MySQL! Comprueba credenciales y servidor.");
        return false;
    }
}

void registrarDispositivo() {
    if (!conn.connected()) {
        Serial.println("No hay conexión a la base de datos para registrar dispositivo");
        return;
    }
    
    // Verificar si el dispositivo ya está registrado
    char consulta[250];
    sprintf(consulta, "SELECT COUNT(*) FROM dispositivos WHERE nombre='%s'", config.nombre_sensor);
    cursor->execute(consulta);
    
    // Leer resultado
    column_names *cols = cursor->get_columns();
    row_values *row = NULL;
    int deviceCount = 0;
    
    do {
        row = cursor->get_next_row();
        if (row) {
            deviceCount = atoi(row->values[0]);
        }
    } while (row != NULL);
    
    if (deviceCount > 0) {
        Serial.println("Dispositivo ya registrado");
    } else {
        // Registrar dispositivo
        for (int intento = 1; intento <= 5; intento++) {
            char insertQuery[350];
            sprintf(insertQuery, "INSERT INTO dispositivos (nombre, ubicacion, direccion_ip, direccion_mac) VALUES ('%s', '%s', '%s', '%s')",
                    config.nombre_sensor,config.ubicacion, WiFi.localIP().toString().c_str(), config.mac);
            
            Serial.print("Intentando registrar dispositivo (intento ");
            Serial.print(intento);
            Serial.print("/5): ");
            
            if (cursor->execute(insertQuery)) {
                Serial.print("Dispositivo ");
                Serial.print(config.nombre_sensor);
                Serial.println(" registrado correctamente");
                break;
            } else {
                Serial.println("Error al registrar dispositivo");
                if (intento == 5) {
                    Serial.println("Se alcanzó el máximo número de intentos");
                } else {
                    delay(3000);
                }
            }
        }
    }
}

void leerSensores() {
    // Leer temperatura y humedad
    float h = dht.readHumidity();
    float t = dht.readTemperature();
    
    // Leer valor de calidad de aire MQ135 calibrado
    MQ135.update();
    float valorMQ135 = MQ135.readSensor() + 400;  // Fixed: was incorrectly using String()
    
    // Leer nivel de luz
    float lux = lightSensor.readLightLevel();
    // Leer nivel de ruido
    int valorRuido = analogRead(PIN_SONIDO);
    // 2024-12-19: Aplicamos calibración: restamos offset y dividimos por la pendiente
    float dB = (valorRuido - offset) / factor;
    delay(200);
    
    // Obtener fecha y hora actual
    DateTime ahora = rtc.now();
    char fechaHora[20];
    sprintf(fechaHora, "%04d-%02d-%02d %02d:%02d:%02d", 
            ahora.year(), ahora.month(), ahora.day(), 
            ahora.hour(), ahora.minute(), ahora.second());
    
    // Mostrar datos
    Serial.println("\n==== Lectura de Sensores ====");
    Serial.print("Fecha y hora: ");
    Serial.println(fechaHora);
    
    if (isnan(h) || isnan(t)) {
        Serial.println("Error leyendo sensor DHT22");
    } else {
        Serial.print("Temperatura: ");
        Serial.print(t);
        Serial.println(" °C");
        Serial.print("Humedad: ");
        Serial.print(h);
        Serial.println(" %");
    }
    
    Serial.print("Calidad aire (CO2): ");
    Serial.print(valorMQ135);
    Serial.println(" ppm");
    
    Serial.print("Nivel luz: ");
    Serial.print(lux);
    Serial.println(" lux");
    
    Serial.print("Nivel ruido: ");
    Serial.print(dB);
    Serial.println(" dB");
}

void enviarRegistroBD() {
    if (!conn.connected()) {
        Serial.println("No hay conexión a la base de datos para enviar registro");
        if (!conectarBaseDatos()) {
            return;
        }
    }
    
    // Leer valores actuales
    float h = dht.readHumidity();
    float t = dht.readTemperature();
    
    // Usar MQ135 calibrado para CO2
    MQ135.update();
    float valorMQ135 = MQ135.readSensor() + 400;
    
    float lux = lightSensor.readLightLevel();
    int valorRuido = analogRead(PIN_SONIDO);
    // aplicamos calibración: restamos offset y dividimos por la pendiente
    float dB = (valorRuido - offset) / factor;
    
    // Obtener fecha y hora actual
    DateTime ahora = rtc.now();
    char fechaHora[20];
    sprintf(fechaHora, "%04d-%02d-%02d %02d:%02d:%02d", 
            ahora.year(), ahora.month(), ahora.day(), 
            ahora.hour(), ahora.minute(), ahora.second());
    
    // Preparar consulta SQL adaptada a la estructura de la tabla:
    char insertQuery[350];
    
    // Manejar posibles fallos de lectura del sensor DHT22
    if (isnan(h) || isnan(t)) {
        sprintf(insertQuery, "INSERT INTO registros (sensor_id, temperatura, humedad, ruido, co2, lux, fecha_hora) VALUES ('%s', NULL, NULL, %.2f, %.0f, %d, '%s')",
                config.nombre_sensor, dB, valorMQ135, (int)lux, fechaHora);
    } else {
        sprintf(insertQuery, "INSERT INTO registros (sensor_id, temperatura, humedad, ruido, co2, lux, fecha_hora) VALUES ('%s', %.2f, %.2f, %.2f, %.0f, %d, '%s')",
                config.nombre_sensor, t, h, dB, valorMQ135, (int)lux, fechaHora);
    }
    
    // Intentar ejecutar la consulta
    for (int intento = 1; intento <= 5; intento++) {
        Serial.print("Intentando enviar registro a BD (intento ");
        Serial.print(intento);
        Serial.print("/5)...");

        if (cursor->execute(insertQuery)) {
            Serial.println(" ✓ Registro en la BBDD correcto");
            break;
        } else {
            Serial.println(" ✗ Error al enviar registro a la BD");
            if (intento == 5) {
                Serial.println("Se alcanzó el máximo número de intentos");
            } else {
                // Reconectar en caso de error y esperar un poco
                if (conectarBaseDatos()) {
                    Serial.println("Reconexión exitosa, reintentando...");
                }
                delay(3000);
            }
        }
    }
}

bool estaEnHorarioOperativo() {
    DateTime ahora = rtc.now();
    int horaActual = ahora.hour();
    int minutoActual = ahora.minute();
    
    // Convertir a minutos para facilitar comparación
    int tiempoActual = horaActual * 60 + minutoActual;
    int tiempoInicio = config.hora_inicio * 60 + config.minuto_inicio;
    int tiempoFin = config.hora_fin * 60 + config.minuto_fin;
    
    // Si el tiempo de fin es menor que el tiempo de inicio, significa que cruza la medianoche
    if (tiempoFin < tiempoInicio) {
        return (tiempoActual >= tiempoInicio) || (tiempoActual <= tiempoFin);
    } else {
        return (tiempoActual >= tiempoInicio) && (tiempoActual <= tiempoFin);
    }
}

String obtenerDireccionMAC() {
    byte mac[6];
    WiFi.macAddress(mac);
    char macStr[18];
    sprintf(macStr, "%02X:%02X:%02X:%02X:%02X:%02X", mac[5], mac[4], mac[3], mac[2], mac[1], mac[0]);
    return String(macStr);
}

String generarNombreSensor(String mac) {
    // Eliminar los dos puntos de la dirección MAC
    String macSinFormato = mac;
    macSinFormato.replace(":", "");
    
    // Tomar los últimos 8 caracteres
    String ultimosOcho = macSinFormato.substring(macSinFormato.length() - 8);
    
    // Generar nombre del sensor
    return "Sensor-" + ultimosOcho;
}

void enviarPaginaConfirmacion(WiFiClient& cliente) {
    Serial.println("Enviando página de confirmación");
    
    // Enviar encabezados HTTP
    cliente.println("HTTP/1.1 200 OK");
    cliente.println("Content-Type: text/html");
    cliente.println("Connection: close");
    cliente.println();
    
    // Inicio documento HTML
    cliente.println("<!DOCTYPE HTML>");
    cliente.println("<html>");
    cliente.println("<head>");
    cliente.println("<meta charset='UTF-8'>");
    cliente.println("<meta name='viewport' content='width=device-width, initial-scale=1'>");
    cliente.println("<title>Configuración Guardada</title>");
    
    // Estilos CSS
    cliente.println("<style>");
    cliente.println("body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 24px; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); color: #323130; min-height: 100vh; display: flex; align-items: center; justify-content: center; }");
    cliente.println("h1 { color: #0078d4; text-align: center; font-weight: 600; font-size: 28px; margin-bottom: 16px; }");
    cliente.println(".container { max-width: 500px; background: rgba(255, 255, 255, 0.95); padding: 48px 32px; border-radius: 12px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2); text-align: center; }");
    cliente.println(".success { color: #107c10; font-weight: 600; margin: 24px 0; font-size: 18px; padding: 16px; background: rgba(16, 124, 16, 0.1); border-radius: 8px; border-left: 4px solid #107c10; }");
    cliente.println("p { line-height: 1.6; font-size: 16px; margin-bottom: 16px; color: #605e5c; }");
    cliente.println("</style>");
    cliente.println("<meta http-equiv='refresh' content='5;url=/'>");
    cliente.println("</head>");
    
    // Cuerpo del documento
    cliente.println("<body>");
    cliente.println("<div class='container'>");
    cliente.println("<h1>Sensor Ambiental</h1>");
    cliente.println("<div class='success'>¡Configuración guardada correctamente!</div>");
    cliente.println("<p>El dispositivo se está reiniciando para aplicar los cambios.</p>");
    cliente.println("<p>Por favor, espere unos momentos...</p>");
    cliente.println("</div>");
    cliente.println("</body>");
    cliente.println("</html>");
    
    Serial.println("Página de confirmación enviada completamente");
}
