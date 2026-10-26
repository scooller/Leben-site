<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Database\Seeder;

class InitialPagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pages = [
            [
                'slug' => 'home',
                'title' => 'Inicio',
                'subtitle' => 'Creando espacios donde lo cotidiano se vuelve extraordinario',
                'badge' => 'Leben',
                'hero_image_url' => '/storage/home/hero-bg.jpg',
                'hero_video_url' => '/storage/video/Banner-Hero-Desktop.mp4',
                'seo_title' => 'Inmobiliaria Leben | Espacios que Transforman',
                'seo_description' => 'Proyectos inmobiliarios de alto estándar en Santiago y el Sur de Chile.',
                'seo_og_image_url' => '/storage/brand/og-image.jpg',
                'is_published' => true,
                'sections' => [
                    [
                        'section_key' => 'diferenciadores',
                        'title' => 'Descubre cómo hacemos la diferencia en la experiencia inmobiliaria',
                        'subtitle' => 'Compromiso y excelencia certificada',
                        'eyebrow' => 'Por qué Leben',
                        'type' => 'features_list',
                        'sort_order' => 1,
                        'is_active' => true,
                        'data' => [
                            'items' => [
                                [
                                    'badge_image' => '/storage/brand/sello-5.svg',
                                    'text' => 'Certificados <b>6 años consecutivos</b> como una de las mejores inmobiliarias para vivir.',
                                ],
                                [
                                    'badge_image' => '/storage/brand/sello4.png',
                                    'text' => '<b>Top-4 Chile y Top-9 Latinoamérica</b> 2025 en satisfacción y acompañamiento al cliente.',
                                ],
                                [
                                    'badge_image' => '/storage/brand/sello-best-place.svg',
                                    'text' => 'Más de <b>3.000 clientes felices</b> respaldan nuestro estándar de entrega.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'project_of_month',
                        'title' => 'María Gracia en Leben',
                        'subtitle' => 'Visita exclusiva a Edificio INN Puerto Varas',
                        'eyebrow' => 'Proyecto Destacado',
                        'type' => 'custom',
                        'sort_order' => 2,
                        'is_active' => true,
                        'data' => [
                            'description' => 'La creadora de contenido y conductora María Gracia Subercaseaux visitó y recomendó Edificio INN en Puerto Varas.',
                            'photo_url' => '/storage/inn/RAPC_EXT_Elevacioen-Tarde_Media16.jpg',
                            'video_url' => '/storage/video/maria-gracia.mp4',
                            'cta' => [
                                'label' => 'Ver proyecto INN',
                                'url' => '/proyectos/inn',
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'videos_obras',
                        'title' => '06 Obras seleccionadas',
                        'subtitle' => 'Proyectos terminados y en desarrollo con altos estándares de diseño y sustentabilidad.',
                        'eyebrow' => 'Obras & Arquitectura',
                        'type' => 'custom',
                        'sort_order' => 3,
                        'is_active' => true,
                        'data' => [
                            'counter' => '06',
                            'highlight_image' => '/storage/inn/inn-overview-1.jpg',
                        ],
                    ],
                    [
                        'section_key' => 'banner_cta',
                        'title' => 'Encuentra tu próximo hogar o inversión',
                        'subtitle' => 'Asesoría experta y financiamiento a tu medida',
                        'eyebrow' => 'Tu Nuevo Espacio',
                        'type' => 'banner_cta',
                        'sort_order' => 4,
                        'is_active' => true,
                        'data' => [
                            'button_label' => 'Cotizar ahora',
                            'button_url' => '/cotizador',
                            'image_url' => '/storage/home/Mesa de trabajo.jpg',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'nosotros',
                'title' => 'Nosotros',
                'subtitle' => 'Espacios que inspiran tu manera de vivir',
                'badge' => 'Historia & Trayectoria',
                'hero_image_url' => '/storage/nosotros/hero-nosotros.jpg',
                'hero_video_url' => null,
                'seo_title' => 'Nosotros | Inmobiliaria Leben',
                'seo_description' => 'Conoce la historia, visión y trayectoria de Inmobiliaria Leben con más de 18 proyectos desarrollados.',
                'seo_og_image_url' => '/storage/brand/og-image.jpg',
                'is_published' => true,
                'sections' => [
                    [
                        'section_key' => 'historia',
                        'title' => 'Nuestra Trayectoria',
                        'subtitle' => 'Diseño y arquitectura que transforman vidas',
                        'eyebrow' => 'Trayectoria',
                        'type' => 'cards_grid',
                        'sort_order' => 1,
                        'is_active' => true,
                        'data' => [
                            'paragraphs' => [
                                'En Leben creemos que el diseño y la arquitectura tienen el poder de transformar la vida cotidiana.',
                                'Con más de 15 años en el mercado inmobiliario, hemos desarrollado proyectos emblemáticos tanto en Santiago como en el Sur de Chile, destacando por nuestro compromiso con la calidad y la satisfacción del cliente.',
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'proyectos_entregados',
                        'title' => '18 Proyectos Entregados',
                        'subtitle' => 'Historias construidas con solidez',
                        'eyebrow' => 'Portafolio Histórico',
                        'type' => 'cards_grid',
                        'sort_order' => 2,
                        'is_active' => true,
                        'data' => [
                            'items' => [
                                ['name' => 'Edificio MOOD', 'comuna' => 'Ñuñoa'],
                                ['name' => 'Edificio OWN', 'comuna' => 'Providencia'],
                                ['name' => 'Edificio SUECIA', 'comuna' => 'Providencia'],
                                ['name' => 'Edificio INDI', 'comuna' => 'Santiago'],
                                ['name' => 'Edificio OKINAWA', 'comuna' => 'Ñuñoa'],
                                ['name' => 'Edificio ARGOMEDO', 'comuna' => 'Santiago'],
                                ['name' => 'Edificio INDIGO', 'comuna' => 'Santiago'],
                                ['name' => 'Edificio BAUM', 'comuna' => 'Ñuñoa'],
                                ['name' => 'Edificio LIVINGTOWN', 'comuna' => 'San Miguel'],
                                ['name' => 'Edificio SANTO', 'comuna' => 'Santiago'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'informacion-de-la-empresa',
                'title' => 'Información de la Empresa',
                'subtitle' => 'Transparencia y respaldo corporativo',
                'badge' => 'Corporativo',
                'hero_image_url' => null,
                'hero_video_url' => null,
                'seo_title' => 'Información de la Empresa | Inmobiliaria Leben',
                'seo_description' => 'Datos legales, razón social y representación legal de Inmobiliaria Leben.',
                'seo_og_image_url' => null,
                'is_published' => true,
                'sections' => [
                    [
                        'section_key' => 'company_details',
                        'title' => 'Datos Corporativos',
                        'subtitle' => 'Inmobiliaria Cenit Limitada',
                        'eyebrow' => 'Identificación Legal',
                        'type' => 'custom',
                        'sort_order' => 1,
                        'is_active' => true,
                        'data' => [
                            'razon_social' => 'Inmobiliaria Cenit Limitada',
                            'rut' => '76.065.957-6',
                            'representante_legal' => 'Juan Ignacio Pinto',
                            'rut_representante' => '15.378.760-3',
                            'direccion' => 'Dr. Manuel Barros Borgoño 386, Providencia, Santiago',
                            'contacto_email' => 'info@ileben.cl',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'proceso-reserva-en-linea',
                'title' => 'Proceso de Reserva en Línea y Retracto',
                'subtitle' => 'Reserva tu departamento en 8 simples pasos',
                'badge' => 'Guía de Compra',
                'hero_image_url' => null,
                'hero_video_url' => null,
                'seo_title' => 'Proceso de Reserva en Línea | Inmobiliaria Leben',
                'seo_description' => 'Guía paso a paso para reservar tu unidad online y política de retracto legal.',
                'seo_og_image_url' => null,
                'is_published' => true,
                'sections' => [
                    [
                        'section_key' => 'steps',
                        'title' => 'Paso a paso de tu reserva',
                        'subtitle' => 'Compra segura 100% digital',
                        'eyebrow' => 'Metodología',
                        'type' => 'features_list',
                        'sort_order' => 1,
                        'is_active' => true,
                        'data' => [
                            'retract_policy' => 'Tienes derecho a retracto durante 10 días hábiles posteriores al pago de la reserva.',
                            'steps' => [
                                ['step' => 1, 'title' => 'Selecciona tu unidad', 'description' => 'Elige la tipología, piso y orientación que mejor se adapte a ti.'],
                                ['step' => 2, 'title' => 'Revisa la cotización', 'description' => 'Verifica precio lista, descuentos vigentes y formas de pago.'],
                                ['step' => 3, 'title' => 'Ingresa tus datos personales', 'description' => 'Nombre, RUT, correo electrónico y teléfono de contacto.'],
                                ['step' => 4, 'title' => 'Acepta términos y condiciones', 'description' => 'Revisa las bases de la promoción y políticas de privacidad.'],
                                ['step' => 5, 'title' => 'Paga la reserva en línea', 'description' => 'A través de Webpay Plus o Mercado Pago de forma 100% segura.'],
                                ['step' => 6, 'title' => 'Confirmación inmediata', 'description' => 'Recibirás un comprobante digital y tu unidad quedará bloqueada.'],
                                ['step' => 7, 'title' => 'Contacto de tu asesor', 'description' => 'Un ejecutivo comercial se comunicará contigo para guiar la promesa.'],
                                ['step' => 8, 'title' => 'Firma de promesa de compraventa', 'description' => 'Protocolización dentro de los plazos estipulados.'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'bases-legales',
                'title' => 'Bases Legales de Promoción',
                'subtitle' => 'Protocolización notarial y condiciones de campaña',
                'badge' => 'Legal',
                'hero_image_url' => null,
                'hero_video_url' => null,
                'seo_title' => 'Bases Legales | Inmobiliaria Leben',
                'seo_description' => 'Términos, condiciones y bases protocolizadas de promociones vigentes.',
                'seo_og_image_url' => null,
                'is_published' => true,
                'sections' => [
                    [
                        'section_key' => 'legal_notice',
                        'title' => 'Protocolización Notarial',
                        'subtitle' => 'Bases vigentes',
                        'eyebrow' => 'Legal',
                        'type' => 'custom',
                        'sort_order' => 1,
                        'is_active' => true,
                        'data' => [
                            'notaria' => 'Notaría San Martín Schröder',
                            'documento_url' => '/storage/legal/20251203-Bases-Promocion.pdf',
                            'disclaimer' => 'La promoción se encuentra adscrita en las Bases de promoción protocolizadas en Notaría San Martín N° 17917-2025.',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'trabaja-en-leben',
                'title' => 'Trabaja en Leben',
                'subtitle' => 'Súmate a un equipo apasionado por la excelencia inmobiliaria',
                'badge' => 'Carreras',
                'hero_image_url' => null,
                'hero_video_url' => null,
                'seo_title' => 'Trabaja en Leben | Oportunidades Laborales',
                'seo_description' => 'Postula a nuestras vacantes abiertas en Inmobiliaria Leben.',
                'seo_og_image_url' => null,
                'is_published' => true,
                'sections' => [
                    [
                        'section_key' => 'convocatoria',
                        'title' => '¿Por qué trabajar con nosotros?',
                        'subtitle' => 'Desarrollo profesional y cultura de diseño',
                        'eyebrow' => 'Talento',
                        'type' => 'custom',
                        'sort_order' => 1,
                        'is_active' => true,
                        'data' => [
                            'email' => 'seleccion@ileben.cl',
                            'description' => 'Buscamos talentos comprometidos con el diseño, la innovación y el servicio al cliente. Envía tu currículum indicando en el asunto tu área de interés.',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'brokers',
                'title' => 'Círculo de Brokers Leben',
                'subtitle' => 'Multiplica tus ventas y accede a comisiones preferenciales',
                'badge' => 'Círculo Leben',
                'hero_image_url' => '/storage/brokers/hero-brokers.jpg',
                'hero_video_url' => null,
                'seo_title' => 'Brokers | Círculo de Partners Leben',
                'seo_description' => 'Alianza para corredores y brokers inmobiliarios con comisiones de hasta 3.0% y liquidaciones rápidas.',
                'seo_og_image_url' => '/storage/brand/og-image.jpg',
                'is_published' => true,
                'sections' => [
                    [
                        'section_key' => 'tiers',
                        'title' => 'Niveles y Beneficios de Corretaje',
                        'subtitle' => 'Categorías diseñadas para potenciar tu gestión',
                        'eyebrow' => 'Programa de Partners',
                        'type' => 'cards_grid',
                        'sort_order' => 1,
                        'is_active' => true,
                        'data' => [
                            'tiers' => [
                                [
                                    'name' => 'Silver Partner',
                                    'volume' => 'Hasta 5.000 UF',
                                    'commission' => '2.0% + IVA',
                                    'payment_term' => '15 días contra promesa',
                                    'perks' => ['Brochures digitales', 'Asesor dedicado'],
                                ],
                                [
                                    'name' => 'Gold Partner',
                                    'volume' => '5.000 a 15.000 UF',
                                    'commission' => '2.5% + IVA',
                                    'payment_term' => '10 días contra promesa',
                                    'perks' => ['Renders 4K y material exclusivo', 'Eventos de lanzamiento privados', 'Asesor preferencial'],
                                ],
                                [
                                    'name' => 'Black Partner',
                                    'volume' => 'Más de 15.000 UF',
                                    'commission' => '3.0% + IVA',
                                    'payment_term' => '5 días contra promesa',
                                    'perks' => ['Comisión máxima', 'Soporte legal directo', 'Pre-venta exclusiva', 'Incentivos por metas'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'invest',
                'title' => 'Inversión Inmobiliaria Inteligente',
                'subtitle' => 'Rentabiliza tu patrimonio con asesoría experta y beneficios únicos',
                'badge' => 'Sin Pie',
                'hero_image_url' => '/storage/invest/hero-invest.jpg',
                'hero_video_url' => null,
                'seo_title' => 'Invest | Inversión Inmobiliaria Sin Pie con Leben',
                'seo_description' => 'Opciones de inversión inmobiliaria con pie en cuotas, arriendo garantizado y beneficios tributarios.',
                'seo_og_image_url' => '/storage/brand/og-image.jpg',
                'is_published' => true,
                'sections' => [
                    [
                        'section_key' => 'benefits',
                        'title' => 'Beneficios de Invertir en Leben',
                        'subtitle' => 'Modelo pensado para el inversionista',
                        'eyebrow' => 'Rentabilidad',
                        'type' => 'features_list',
                        'sort_order' => 1,
                        'is_active' => true,
                        'data' => [
                            'items' => [
                                ['title' => 'Pago de Pie en Cuotas', 'description' => 'Hasta 48 cuotas con tarjeta de crédito sin interés.'],
                                ['title' => 'Arriendo Garantizado', 'description' => 'Opciones de hasta 36 meses de arriendo asegurado.'],
                                ['title' => 'Gestión Integral', 'description' => 'Administración de tu propiedad con partners certificados.'],
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'faq',
                        'title' => 'Preguntas Frecuentes de Inversión',
                        'subtitle' => 'Todo lo que necesitas saber antes de invertir',
                        'eyebrow' => 'FAQ Inmobiliario',
                        'type' => 'faq_accordion',
                        'sort_order' => 2,
                        'is_active' => true,
                        'data' => [
                            'faqs' => [
                                ['q' => '¿Qué es el Cap Rate y cómo se calcula?', 'a' => 'El Cap Rate es la tasa de capitalización que mide la rentabilidad anual de un inmueble respecto a su valor de compra.'],
                                ['q' => '¿En qué consiste el beneficio tributario del Art. 55 bis?', 'a' => 'Permite deducir de la renta bruta imponible los intereses pagados por créditos hipotecarios destinados a adquirir una vivienda.'],
                                ['q' => '¿Cómo opera la devolución de IVA en compra de departamentos?', 'a' => 'Bajo la modalidad de inversión con arriendo amoblado o afecto a IVA, es posible recuperar el crédito fiscal del IVA de compra según normativa del SII.'],
                                ['q' => '¿Cuáles son las ventajas del DFL-2?', 'a' => 'Exención del 50% en contribuciones por periodos determinados, exención de impuesto de timbres y estampillas preferencial y libre de impuesto a la herencia.'],
                                ['q' => '¿Puedo invertir sin tener todo el pie disponible hoy?', 'a' => 'Sí, contamos con planes de financiamiento de pie flexible en verde y pre-venta.'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'cookies',
                'title' => 'Declaración de Cookies',
                'subtitle' => 'Cumplimiento Ley N° 21.719 de Protección de Datos Personales',
                'badge' => 'Privacidad',
                'hero_image_url' => null,
                'hero_video_url' => null,
                'seo_title' => 'Política de Cookies | Inmobiliaria Leben',
                'seo_description' => 'Información sobre cookies técnicas, analíticas y de preferencias conforme a Ley 21.719.',
                'seo_og_image_url' => null,
                'is_published' => true,
                'sections' => [
                    [
                        'section_key' => 'legal_text',
                        'title' => 'Finalidades de Uso de Cookies',
                        'subtitle' => 'Derechos ARCOP-B',
                        'eyebrow' => 'Protección de Datos',
                        'type' => 'custom',
                        'sort_order' => 1,
                        'is_active' => true,
                        'data' => [
                            'law' => 'Ley 21.719',
                            'rights' => 'Derechos ARCOP-B (Acceso, Rectificación, Cancelación, Oposición, Portabilidad y Bloqueo)',
                            'categories' => [
                                ['name' => 'Técnicas y Necesarias', 'description' => 'Esenciales para el funcionamiento del cotizador y la pasarela de pagos.'],
                                ['name' => 'Analíticas', 'description' => 'Permiten medir navegación anónima para optimizar la experiencia de usuario.'],
                                ['name' => 'Marketing y Preferencias', 'description' => 'Personalizan contenido publicitario y conservan selecciones de filtros.'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($pages as $pData) {
            $sections = $pData['sections'] ?? [];
            unset($pData['sections']);

            $page = Page::updateOrCreate(['slug' => $pData['slug']], $pData);

            // Sync sections
            foreach ($sections as $sData) {
                PageSection::updateOrCreate(
                    [
                        'page_id' => $page->id,
                        'section_key' => $sData['section_key'],
                    ],
                    $sData
                );
            }
        }
    }
}
