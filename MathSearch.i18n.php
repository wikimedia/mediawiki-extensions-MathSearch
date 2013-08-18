<?php
/**
 * Internationalisation for MathSearch
 *
 * @file
 * @ingroup Extensions
 */
$messages = array();

/** English
 * @author Moritz Schubotz
 */
$messages['en'] = array(
	'mathsearch' => 'Math search', // Special page
	'formulainfo' => 'Formula info', // Special page
	'specialpages-group-mathsearch' => 'Math search',
	'mathsearch-desc' => 'Integrates the [http://search.mathweb.org/about.html MathWeb Search] engine',
	'getequationsbyquery' => 'Get equations by query', // Special page
	'xquerygenerator' => 'XQuery generator', // Special page
	'mathdebug' => 'Test Renderer', // Special page
	'mathmode_0' => 'MW_MATH_PNG',
	'mathmode_1' => 'MW_MATH_SIMPLE',
	'mathmode_2' => 'MW_MATH_HTML',
	'mathmode_3' => 'MW_MATH_SOURCE',
	'mathmode_4' => 'MW_MATH_MODERN',
	'mathmode_5' => 'MW_MATH_MATHML',
	'mathmode_6' => 'MW_MATH_MATHJAX',
	'mathmode_7' => 'MW_MATH_LATEXML',
	'mathmode_7+' => 'MW_MATH_LATEXML_JAX', //TODO: Remove that construct
);

/** Message documentation (Message documentation)
 * @author Moritz Schubotz
 * @author Shirayuki
 */
$messages['qqq'] = array(
	'mathsearch' => '{{doc-special|MathSearch}}
"Math Search" is the name of the MediaWiki extension which integrates the [http://search.mathweb.org/ MathWeb Search] engine.

Math Search is used to search for a formula based on their MathML representation.
{{Identical|Math search}}',
	'formulainfo' => '{{doc-special|FormulaInfo}}
The special page displays technical information about the formula, e.g. the variables it contains and information about rendering etc.',
	'specialpages-group-mathsearch' => '{{doc-special-group|that=are related to the extension MathSearch|like=[[Special:MathSearch]], [[Special:FormulaInfo]], [[Special:GetEquationsByQuery]], [[Special:XQueryGenerator]]}}
"Math Search" is also the name of the MediaWiki extension which integrates the [http://search.mathweb.org/ MathWeb Search] engine.
{{Identical|Math search}}',
	'mathsearch-desc' => '{{desc|name=Math Search|http://www.mediawiki.org/wiki/Extension:MathSearch}}',
	'getequationsbyquery' => '{{doc-special|GetEquationByQuery}}',
	'xquerygenerator' => '{{doc-special|XQueryGenerator}}',
	'mathdebug' => '{{doc-special|MathDebug}}',
);

/** Asturian (asturianu)
 * @author Xuacu
 */
$messages['ast'] = array(
	'mathsearch' => 'Gueta matemática',
	'formulainfo' => 'Información de la fórmula',
	'specialpages-group-mathsearch' => 'Gueta matemática',
	'mathsearch-desc' => 'Integra el motor de gueta [http://search.mathweb.org/about.html MathWeb Search]',
	'getequationsbyquery' => 'Algamar ecuaciones por consulta',
	'xquerygenerator' => 'Xenerador XQuery',
	'mathdebug' => 'Probar el dibuxador',
);

/** Breton (brezhoneg)
 * @author Y-M D
 */
$messages['br'] = array(
	'mathsearch-desc' => 'Enframmañ al lusker [http://search.mathweb.org/about.html MathWeb Search]',
);

/** German (Deutsch)
 * @author Metalhead64
 */
$messages['de'] = array(
	'mathsearch' => 'Math-Suche',
	'formulainfo' => 'Formelinformation',
	'specialpages-group-mathsearch' => 'Math-Suche',
	'mathsearch-desc' => 'Ermöglicht die Integration der [http://search.mathweb.org/about.html MathWeb-Suchmaschine]',
	'getequationsbyquery' => 'Gleichungen von Abfrage erhalten',
	'xquerygenerator' => 'XQuery-Generator',
	'mathdebug' => 'Testrenderer',
);

/** Lower Sorbian (dolnoserbski)
 * @author Michawiki
 */
$messages['dsb'] = array(
	'mathsearch' => 'Math-pytanje',
	'formulainfo' => 'Formulowe informacije',
	'specialpages-group-mathsearch' => 'Math-pytanje',
	'mathsearch-desc' => 'Integrěrujo [http://search.mathweb.org/about.html pytawu MathWeb]',
);

/** Spanish (español)
 * @author Ralgis
 */
$messages['es'] = array(
	'mathsearch-desc' => 'Integra el motor [http://search.mathweb.org/about.html MathWeb Search]',
);

/** French (français)
 * @author Crochet.david
 * @author Gomoko
 * @author Metroitendo
 */
$messages['fr'] = array(
	'mathsearch' => 'Recherche mathématique',
	'formulainfo' => 'Information sur la formule',
	'specialpages-group-mathsearch' => 'Recherche mathématique',
	'mathsearch-desc' => 'Intégrer le moteur [http://search.mathweb.org/about.html MathWeb Search]',
	'getequationsbyquery' => 'Obtenir les équations par requête',
	'xquerygenerator' => 'Générateur XQuery',
	'mathdebug' => 'Rendu de test',
);

/** Galician (galego)
 * @author Toliño
 */
$messages['gl'] = array(
	'mathsearch' => 'Procura matemática',
	'formulainfo' => 'Información sobre a fórmula',
	'specialpages-group-mathsearch' => 'Procura matemática',
	'mathsearch-desc' => 'Integra o motor [http://search.mathweb.org/about.html MathWeb Search]',
	'getequationsbyquery' => 'Obter as ecuacións por pescuda',
	'xquerygenerator' => 'Xerador XQuery',
	'mathdebug' => 'Probar o renderizador',
);

/** Hebrew (עברית)
 * @author Amire80
 */
$messages['he'] = array(
	'mathsearch' => 'חיפוש מתמטיקה',
	'formulainfo' => 'מידע על הנוסחה',
	'specialpages-group-mathsearch' => 'חיפוש מתמטיקה',
	'mathsearch-desc' => 'הוספת תמיכה במנוע החיפוש [http://search.mathweb.org/about.html MathWeb Search]',
	'getequationsbyquery' => 'קבלת משוואות לפי שאילתה',
	'xquerygenerator' => 'מחולל XQuery',
);

/** Upper Sorbian (hornjoserbsce)
 * @author Michawiki
 */
$messages['hsb'] = array(
	'mathsearch' => 'Math-pytanje',
	'formulainfo' => 'Formlowe informacije',
	'specialpages-group-mathsearch' => 'Math-pytanje',
	'mathsearch-desc' => 'Integruje [http://search.mathweb.org/about.html pytawu MathWeb]',
);

/** Italian (italiano)
 * @author Beta16
 */
$messages['it'] = array(
	'mathsearch-desc' => 'Integra il motore di ricerca [http://search.mathweb.org/about.html MathWeb]',
);

/** Japanese (日本語)
 * @author Shirayuki
 */
$messages['ja'] = array(
	'mathsearch' => '数式の検索',
	'formulainfo' => '数式の情報',
	'specialpages-group-mathsearch' => '数式の検索',
	'mathsearch-desc' => '[http://search.mathweb.org/about.html MathWeb 検索]エンジンを統合する',
	'getequationsbyquery' => 'クエリによる式の取得',
	'xquerygenerator' => 'XQueryジェネレーター',
);

/** Korean (한국어)
 * @author 아라
 */
$messages['ko'] = array(
	'mathsearch' => '수학 찾기',
	'formulainfo' => '수식 정보',
	'specialpages-group-mathsearch' => '수학 찾기',
	'mathsearch-desc' => '[http://search.mathweb.org/about.html MathWeb 검색] 엔진을 통합합니다',
	'getequationsbyquery' => '쿼리로 방정식 얻기',
	'xquerygenerator' => 'XQuery 생성기',
	'mathdebug' => '테스트 표시기',
);

/** Colognian (Ripoarisch)
 * @author Purodha
 */
$messages['ksh'] = array(
	'formulainfo' => 'Aanjaabe övver Formelle',
);

/** Luxembourgish (Lëtzebuergesch)
 * @author Robby
 */
$messages['lb'] = array(
	'mathsearch' => 'Math Search',
	'formulainfo' => "Informatioun iwwert d'Formel",
	'specialpages-group-mathsearch' => 'Math Search',
	'mathsearch-desc' => "Integréiert d'[http://search.mathweb.org/about.html MathWeb Search] Software",
);

/** Minangkabau (Baso Minangkabau)
 * @author Iwan Novirion
 */
$messages['min'] = array(
	'mathsearch' => 'Pancarian Cocok',
	'formulainfo' => 'Info Formula',
	'specialpages-group-mathsearch' => 'Pancarian Cocok',
	'mathsearch-desc' => 'Integrasi [http://search.mathweb.org/about.html Masin Pancari MathWeb]',
);

/** Macedonian (македонски)
 * @author Bjankuloski06
 */
$messages['mk'] = array(
	'mathsearch' => 'Math-пребарување',
	'formulainfo' => 'Инфо за формула',
	'specialpages-group-mathsearch' => 'Math-пребарување',
	'mathsearch-desc' => 'Овозможува интеграција на пребарувачот [http://search.mathweb.org/about.html MathWeb]',
	'getequationsbyquery' => 'Дај равенки по барање',
	'xquerygenerator' => 'Создавач на XQuery',
	'mathdebug' => 'Текстоисписник',
);

/** Malay (Bahasa Melayu)
 * @author Anakmalaysia
 */
$messages['ms'] = array(
	'mathsearch' => 'Pencarian matematik',
	'formulainfo' => 'Maklumat rumus',
	'specialpages-group-mathsearch' => 'Pencarian matematik',
	'mathsearch-desc' => 'Menyepadukan enjin [http://search.mathweb.org/about.html MathWeb Search]',
	'getequationsbyquery' => 'Dapatkan persamaan dengan pertanyaan',
	'xquerygenerator' => 'Penjana XQuery',
	'mathdebug' => 'Pemapar Uji',
);

/** Maltese (Malti)
 * @author Chrisportelli
 */
$messages['mt'] = array(
	'mathsearch-desc' => "Tintegra l-mutur ta' tfittxija[http://search.mathweb.org/about.html MathWeb]",
);

/** Dutch (Nederlands)
 * @author Konovalov
 * @author Rcdeboer
 * @author Siebrand
 */
$messages['nl'] = array(
	'mathsearch' => 'Wiskundig zoeken',
	'formulainfo' => 'Formulegegevens',
	'specialpages-group-mathsearch' => 'Wiskundig zoeken',
	'mathsearch-desc' => 'Integreert de [http://search.mathweb.org/about.html MathWeb Zoekmachine]',
	'getequationsbyquery' => 'Vergelijkingen zoeken',
	'xquerygenerator' => 'XQuerygenerator',
	'mathdebug' => 'Verwerking testen',
);

/** Polish (polski)
 * @author Matma Rex
 * @author Woytecr
 */
$messages['pl'] = array(
	'mathsearch' => 'Wyszukiwanie wzorów',
	'specialpages-group-mathsearch' => 'Wyszukiwanie wzorów',
	'mathsearch-desc' => 'Integruje wyszukiwarkę [http://search.mathweb.org/about.html MathWeb Search]',
	'getequationsbyquery' => 'Pobierz równania przez zapytania',
);

/** Piedmontese (Piemontèis)
 * @author Dragonòt
 */
$messages['pms'] = array(
	'mathsearch-desc' => 'A ìntegra ël motor [http://search.mathweb.org/about.html MathWeb Search]',
);

/** tarandíne (tarandíne)
 * @author Joetaras
 */
$messages['roa-tara'] = array(
	'mathsearch' => 'Math Ricerche',
	'formulainfo' => "'Mbormaziune sus a Formule",
	'specialpages-group-mathsearch' => 'Math Ricerche',
	'mathsearch-desc' => "Integre 'u motore de [http://search.mathweb.org/about.html Ricerche MathWeb]",
	'getequationsbyquery' => "Pigghie le equaziune cu l'inderrogazione",
	'xquerygenerator' => 'Generatore XQuery',
	'mathdebug' => "Test d'u render",
);

/** Ukrainian (українська)
 * @author Andriykopanytsia
 * @author Base
 */
$messages['uk'] = array(
	'mathsearch' => 'Математичний пошук',
	'formulainfo' => 'Відомості про формулу',
	'specialpages-group-mathsearch' => 'Математичний пошук',
	'mathsearch-desc' => 'Інтегрує рушій [http://search.mathweb.org/about.html MathWeb Search]',
	'getequationsbyquery' => 'Отримання рівнянь за запитом',
	'xquerygenerator' => 'XQuery генератор',
	'mathdebug' => 'Тест візуалізатора',
);

/** Urdu (اردو)
 * @author Noor2020
 */
$messages['ur'] = array(
	'mathdebug' => 'آزمائشی سرانجام کار',
);

/** Simplified Chinese (中文（简体）‎)
 * @author Yfdyh000
 */
$messages['zh-hans'] = array(
	'mathsearch-desc' => '整合[http://search.mathweb.org/about.html MathWeb 搜索]引擎',
);
