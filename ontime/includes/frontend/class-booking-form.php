<?php
/**
 * Frontend booking form (Singleton).
 *
 * Registers the [ontime_booking_form] shortcode and renders a mobile-first,
 * step-by-step booking widget. All step transitions use secure AJAX
 * endpoints (nonce-gated, sanitized input). Uses pure Vanilla JS â no jQuery.
 *
 * @package OnTime
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OnTime_Frontend_Booking_Form {

	/** @since 0.1.0 @var OnTime_Frontend_Booking_Form|null */
	private static $instance = null;

	/** @since 0.5.0 @var bool Whether assets were enqueued for this request. */
	private $enqueued = false;

	/**
	 * Singleton accessor.
	 *
	 * @since 0.1.0
	 * @return OnTime_Frontend_Booking_Form
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Register shortcode + AJAX endpoints.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	private function init() {
		add_shortcode( 'ontime_booking_form', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ) );

		// AJAX endpoints (both logged-in and guests).
		add_action( 'wp_ajax_ontime_get_services', array( $this, 'ajax_get_services' ) );
		add_action( 'wp_ajax_nopriv_ontime_get_services', array( $this, 'ajax_get_services' ) );
		add_action( 'wp_ajax_ontime_get_slots', array( $this, 'ajax_get_slots' ) );
		add_action( 'wp_ajax_nopriv_ontime_get_slots', array( $this, 'ajax_get_slots' ) );
		add_action( 'wp_ajax_ontime_submit_booking', array( $this, 'ajax_submit_booking' ) );
		add_action( 'wp_ajax_nopriv_ontime_submit_booking', array( $this, 'ajax_submit_booking' ) );
	}

	/**
	 * Enqueue frontend assets only when the shortcode is present.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function maybe_enqueue() {
		if ( ! $this->enqueued ) {
			return;
		}
		wp_enqueue_style(
			'ontime-frontend',
			ONTIME_URL . 'assets/css/ontime-frontend.css',
			array(),
			ONTIME_VERSION
		);
		wp_enqueue_script(
			'ontime-frontend',
			ONTIME_URL . 'assets/js/ontime-frontend.js',
			array(),
			ONTIME_VERSION,
			true
		);
		wp_localize_script( 'ontime-frontend', 'OnTimeData', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'ontime_nonce' ),
			'i18n'    => array(
				'selectService' => __( 'ÙØ·ÙØ§Ù ÛÚ© Ø³Ø±ÙÛØ³ Ø§ÙØªØ®Ø§Ø¨ Ú©ÙÛØ¯.', 'ontime' ),
				'selectDate'    ==> __( 'ÙØ·ÙØ§Ù ÛÚ© ØªØ§Ø±ÛØ® Ø§ÙØªØ®Ø§Ø¨ Ú©ÙÛØ¯.', 'ontime' ),
				'selectSlot'    ==> __( 'ÙØ·ÙØ§Ù ÛÚ© Ø³Ø§Ø¹Øª Ø§ÙØªØ®Ø§Ø¨ Ú©ÙÛØ¯.', 'ontime' ),
				'nameRequired'  => __( 'ÙØ§Ù Ø§ÙØ²Ø§ÙÛ Ø§Ø³Øª.', 'ontime' ),
				'phoneRequired' => __( 'Ø´ÙØ§Ø±Ù ØªÙØ§Ø³ Ø§ÙØ²Ø§Ùã6)ö,ö*Ë	ÛÛ[YIÈ
KBBBIÙ[XZ[[[Y	ÈO×Ê	ö)öã6avã6a6a¶)öav.v*¶*6,H6)ö,ö*Ë	ÛÛ[YIÈ
KBBBIÛØY[ÉÈO×Ê	ö+ö,H6+v)öa6*6)ö,v+ö,6)ö,vãË	ÛÛ[YIÈ
KBBBIÙ\ÜÈO×Ê	ö+¶-ö)öã6,H6,v+6+ö)ö+Ë6+öb6*6)ö,vaÈ6*¶a6)ö-6ªva¶ã6+ËË	ÛÛ[YIÈ
KBBBIÜÝXØÙ\ÜÉÈO×Ê	öa¶b6*6*6-6av)È6*6)È6avb6`v`¶ã6*6*ö*6*6-6+ËË	ÛÛ[YIÈ
KBBBIÛÔÛÝÉÈOO×Ê	öaöã6¡6,ö)ö.v*6(¶,¶)ö+öã6*6,v)öã6)öã6a	¶,vb6,6b6+6b6+È6a¶+ö)ö,xÍË	ÛÛ[YIÈ
KBBBIÛ^	ÈOO×Ê	ö*6.v+öã	Ë	ÛÛ[YIÈ
KBBBIÜ]ÈO×Ê	ö`¶*6a6ã	Ë	ÛÛ[YH(
KBBBIØÛÛ\IÈO×Ê	ö*¶(öã6ã6+È6a¶aö)öã6ã	Ë	ÛÛ[YIÈ
KBBBIÜY\XÝ[ÉÈOO×Ê	ö+ö,H6+v)öa6)öa¶*¶`¶)öa6*6aÈ6+ö,v«ö)öaÈ6o¶,v+ö)ö+¶*Ë	ÛÛ[YIÈ
KBBJKBJH
NÂ_BKÊH
X\È\ÜÙ]ÈÜ[]Y]YH
Ø[YÚ[ÚÜÛÙH[\ÊKH
H
Ú[ÙHKH
]\ÚYH
Â\]]H[Ý[ÛY×Ù[]Y]YJ
HÂBI\ËO[]Y]YYHYNÂ_BKÊH
ÚÜÛÙH[\\Ý]]ÈHÛÚÚ[ÈÚYÙ]ÛÛZ[\H
H
Ú[ÙHKH
H
\[H\^H	]ÈÚÜÛÙH]X]\ËH
]\Ý[ÈSÝ]]H
Â\XXÈ[Ý[Û[\ÜÚÜÛÙJ	]È
HÂBI]ÈHÚÜÛÙWØ]Ê\^JBBIÜÙ\XÙWÚY	ÈOBBIÝ[YIÈO	ÛYÚ	ËBJK	]Ë	ÛÛ[YWØÛÚÚ[×ÙÜIÈ
NÂBI\ËOY×Ù[]Y]YJ
NÂBIÙ\XÙWÚYH
[
H	]ÖÉÜÙ\XÙWÚY	×NÂBI[YHHØ[]^WÚÙ^J	]ÖÉÝ[YI×H
NÂB[ØÜÝ\

NÂBOÏBO]Û\ÜÏHÛ[YK]ÚYÙ]Û[YK][YKOÜXÚÈ\Ø×Ø]	[YH
NÈÏBBY]K\Ù\XÙOHÜXÚÈ\Ø×Ø]	Ù\XÙWÚY
NÈÏBBY]KXZ^HÜXÚÈ\Ø×Ý\
YZ[Ý\
	ØYZ[XZ^	È
H
NÈÏBBO]Û\ÜÏHÛ[YK\Ý\ÈBBBBKHÝ\NÙ\XÙHKOBBBO]Û\ÜÏHÛ[YK\Ý\Û[YK\Ý\LH]K\Ý\HHBBBBOÈÛ\ÜÏHÛ[YK\Ý\]HÜ\Ø×Ú[ÙJ	ö)öa¶*¶+¶)ö*6,ö,vb6ã6,ÉË	ÛÛ[YIÈ
NÈÏÚÏBBBBO]Û\ÜÏHÛ[YK\Ù\XÙK[\ÝYHÛ[YK\Ù\XÙ\ÈÙ]BBBOÙ]BBBOKKHÝ\]HKOBBBO]Û\ÜÏHÛ[YK\Ý\Û[YK\Ý\LÛ[YKZY[]K\Ý\HBBBBOÈÛ\ÜÏHÛ[YK\Ý\]]HÜ\Ø×Ú[ÙJ	ö)öa¶*¶+¶)ö*6*¶)ö,vã6+Ë	ÛÛ[YIÈ
NÈÏÚÏBBBBO]Û\ÜÏHÛ[YKXØ[[\YHÛ[YKXØ[[\BBBBBO]Û\ÜÏHÛ[YKXØ[[]BBBBBBO]Û\OH]ÛÛ\ÜÏHÛ[YKXØ[\]YHÛ[YK\][[Û\][ÎÏØ]ÛBBBBBBOÜ[YHÛ[YK[[Û[X[ÜÜ[BBBBBBO]Û\OH]ÛÛ\ÜÏHÛ[YKXØ[[^YHÛ[YK[^[[Û\][ÎÏØ]ÛBBBBBOÙ]BBBBBO]Û\ÜÏHÛ[YKXØ[YÜYYHÛ[YKXØ[YÜYÙ]BBBBOÙ]BBBOÙ]BBBOKKHÝ\Î[YHÛÝKOBBBO]Û\ÜÏHÛ[YK\Ý\Û[YK\Ý\LÈÛ[YKZY[]K\Ý\HÈBBBBOÈÛ\ÜÏHÛ[YK\Ý\]]HÜ\Ø×Ú[ÙJ	ö)öa¶*¶+¶)ö*6,ö)ö.v*IË	ÛÛ[YIÈ
NÈÏÚÏBBBBO]Û\ÜÏHÛ[YK\ÛÝÈYHÛ[YK\ÛÝÈÙ]BBBOÙ]BBBOKKHÝ\Ý\ÝÛY\[ÈKOBBBO]Û\ÜÏHÛ[YK\Ý\Û[YK\Ý\MÛ[YKZY[]K\Ý\HBBBBOÈÛ\ÜÏHÛ[YK\Ý\]]HÜ\Ø×Ú[ÙJ	ö)ö)öa6)ö.v)ö*6*¶av)ö,ÉË	ÛÛ[YIÈ
NÈÏÚÏBBBOÜHYHÛ[YKXÝ\ÝÛY\YÜHÛ\ÜÏHÛ[YKYÜHBBBBBOX[Û\ÜÏHÛ[YKYY[BBBBBBOÜ[Ü\Ø×Ú[ÙJ	öa¶)öaH6b6a¶)öaH6+¶)öa¶b6)ö+ö«öã	Ë	ÛÛ[YIÈ
NÈÏ
ÜÜ[BBBBBBO[]\OH^[YOHÝ\ÝÛY\Û[YH\]Z\YÏBBBBBOÛX[BBBBBOX[Û\ÜÏHÛ[YKYY[BBBBBBOÜ[Ü\Ø×Ú[ÙJ	ö-6av)ö,vaÈ6*¶av)ö,ÉË	ÛÛ[YIÈ
NÈÏ
ÜÜ[BBBBBBO[]\OH[[YOHÝ\ÝÛY\ÜÛH\]Z\YÏBBBBBOÛX[BBBBBOX[Û\ÜÏHÛ[YKYY[BBBBBBOÜ[Ü\Ø×Ú[ÙJ	ö)öã6avã6a	Ë	ÛÛ[YIÈ
NÈÏÜÜ[BBBBBBO[]\OH[XZ[[YOHÝ\ÝÛY\Ù[XZ[ÏBBBBBOÛX[BBBBBOX[Û\ÜÏHÛ[YKYY[BBBBBBOÜ[Ü\Ø×Ú[ÙJ	ö*¶b6-¶ã6+v)ö*Ë	ÛÛ[YIÈ
NÈÏÜÜ[BBBBBBO^\XH[YOHÝ\ÈÝÜÏHÈÝ^\XOBBBBBOÛX[BBBBOÙÜOBBBOÙ]BBBOKKHÝ\NÛÛ\X][ÛKOBBBO]Û\ÜÏHÛ[YK\Ý\Û[YK\Ý\MHÛ[YKZY[]K\Ý\HHBBBBOÈÛ\ÜÏHÛ[YK\Ý\]]HÜ\Ø×Ú[ÙJ	ö*¶(öã6ã6+È6a¶aö)öã6ã	Ë	ÛÛ[YIÈ
NÈÏÚÏBBBBO]Û\ÜÏHÛ[YK\Ý[[X\HYHÛ[YK\Ý[[X\HÙ]BBBBO]Û\ÜÏHÛ[YK\\Ý[YHÛ[YK\\Ý[Ù]BBBOÙ]BBOÙ]BBO]Û\ÜÏHÛ[YK[]BBBO]Û\OH]ÛÛ\ÜÏHÛ[YKXÛ[YKX\]YHÛ[YK\]Ü\Ø×Ú[ÙJ	ö`¶*6a6ã	Ë	ÛÛ[YIÈ
NÈÏØ]ÛBBBO]Û\OH]ÛÛ\ÜÏHÛ[YKXÛ[YKX[^YHÛ[YK[^Ü\Ø×Ú[ÙJ	ö*6.v+öã	Ë	ÛÛ[YIÈ
NÈÏØ]ÛBBOÙ]BBO]Û\ÜÏHÛ[YK\ÙÜ\ÜÈ\XKZY[HYHBBBO]Û\ÜÏHÛ[YK\ÙÜ\ÜËX\YHÛ[YK\ÙÜ\ÜËX\Ù]BBOÙ]BOÙ]BOÜB\]\ØÙÙ]ØÛX[
NÂ_BKÊKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKH
ÂKÊRV[Ú[Ë
ÂKÊKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKKH
ÂKÊH
\YHHÛ[YHÛÙHÜRV\]Y\ÝËH
H
Ú[ÙHKH
]\ÛÛYHY[YH
Â\]]H[Ý[Û\YWÛÛÙJ
HÂB\]\ÚXÚ×ØZ^ÜY\\	ÛÛ[YWÛÛÙIË	ÛÛÙIË[ÙH
NÂ_BKÊH
RV\ÝXÝ]HÙ\XÙ\ËH
H
Ú[ÙHKH
]\ÚYH
Â\XXÈ[Ý[ÛZ^ÙÙ]ÜÙ\XÙ\Ê
HÂBZY
H	\ËO\YWÛÛÙJ
H
HÂBB]ÜÜÙ[ÚÛÛÙ\Ü\^J	ÛY\ÜØYÙIÈO×Ê	ö+¶-ö)öã6)öava¶ã6*¶ãË	ÛÛ[YIÈ
H
KÈ
NÂB_BBYÛØ[	ÜÂBIXHHÛ[YWÑ]X\ÙN[Ý[ÙJ
KOÙ]ÝXJ	ÜÙ\XÙ\ÉÈ
NÂBKËÈÜÎYÛÜHÛÜ\ÜË\\YÔS[\Û]YÝ\\YBIÙ\XÙ\ÈH	ÜOÙ]Ü\Ý[ÊBBHÑSPÕY[YK\ØÜ\[Û\][ÛXÙHÓHÉX_HÒTH\×ØXÝ]HHHÔTHYTÐÈBBPTVWÐBBJNÂBIÝ]H\^J
NÂBZY
\×Ø\^J	Ù\XÙ\È
H
HÂBBYÜXXÚ
	Ù\XÙ\È\È	È
HÂBBBIÝ]×HH\^JBBBBIÚY	ÈO
[
H	ÖÉÚY	×KBBBBIÛ[YIÈOO\Ø×Ú[
	ÖÉÛ[YI×H
KBBBBIÙ\ØÜ\[ÛÈOO\Ø×Ú[
	ÖÉÙ\ØÜ\[Û×HÏÈ	ÉÈ
KBBBBIÙ\][ÛÈOO
[
H	ÖÉÙ\][Û×KBBBBIÜXÙIÈOOÛ[YWÐØ[[\Ñ[Ú[N[Ý[ÙJ
KO×Ü\ÚX[ÙYÚ]Ê
Ý[ÊH	ÖÉÜXÙI×H
KBBBJNÂBB_BB_BB]ÜÜÙ[ÚÛÛÜÝXØÙ\ÜÊ\^J	ÜÙ\XÙ\ÉÈOO	Ý]
H
NÂ_BKÊH
RVÙ]YHÛÝÈÜH[[H^KH
H
Ú[ÙHKH
]\ÚYH
Â\XXÈ[Ý[ÛZ^ÙÙ]ÜÛÝÊ
HÂBZY
H	\ËO\YWÛÛÙJ
H
HÂBB]ÜÜÙ[ÚÛÛÙ\Ü\^J	ÛY\ÜØYÙIÈO×Ê	ö+¶-ö)öã6)öava¶ã6*¶ãË	ÛÛ[YIÈ
H
KÈ
NÂB_BBIÙ\XÙWÚYH\ÜÙ]
	ÔÔÕÉÜÙ\XÙWÚY	×H
HÈ
[
H	ÔÔÕÉÜÙ\XÙWÚY	×HÂBIÞYX\H\ÜÙ]
	ÔÔÕÉÚÞYX\×H
HÈ
[
H	ÔÔÕÉÚÞYX\×HÂBIÛ[ÛH\ÜÙ]
	ÔÔÕÉÚÛ[Û	×H
HÈ
[
H	ÔÔÕÉÚÛ[Û	×HÂBIÙ^HH\ÜÙ]
	ÔÔÕÉÚÙ^I×H
HÈ
[
H	ÔÔÕÉÚÙ^I×HÂBZY
	Ù\XÙWÚYH	ÞYX\LÌ	Û[ÛH	Û[ÛL	Ù^HH	Ù^HÌH
HÂBB]ÜÜÙ[ÚÛÛÙ\Ü\^J	ÛY\ÜØYÙIÈOO×Ê	öb6,vb6+öã6a¶)öav.v*¶*6,KË	ÛÛ[YIÈ
H
K
NÂB_BBIØ[[\HÛ[YWÐØ[[\Ñ[Ú[N[Ý[ÙJ
NÂBIÛÝÈH	Ø[[\OÙ]ÙYWÜÛÝÊ	Ù\XÙWÚY	ÞYX\	Û[Û	Ù^H
NÂBIÝ]H\^J
NÂBYÜXXÚ
	ÛÝÈ\È	È
HÂBBIÝ]×HH\^JBBBIÝÉÈO
[
H	ËBBBIÛX[	ÈOO	Ø[[\OÜX]Ú[[J	Ë	ÒIÈ
KBBJNÂB_BB]ÜÜÙ[ÚÛÛÜÝXØÙ\ÜÊ\^J	ÜÛÝÉÈOO	Ý]
H
NÂ_BKÊH
RVÝXZ]H]ÈÛÚÚ[È
ÜX]\ÈH[[È\Ú[Y[
KH
H
YHÙ\XÙH\ÈHXÙH[H^[Y[Ø]]Ø^H\ÈÛÛYÝ\YH
]\ÈHY\XÝÝ\ÛÈHÝÜÙ\Ø[Y\XÝÈHØ]]Ø^KH
Ý\Ú\ÙH]\ÈHÝXØÙ\ÜÈY\ÜØYÙHÚ]H\Ú[Y[Ý[[X\KH
H
Ú[ÙHKH
]\ÚYH
Â\XXÈ[Ý[ÛZ^ÜÝXZ]ØÛÚÚ[Ê
HÂBZY
H	\ËO\YWÛÛÙJ
H
HÂBB]ÜÜÙ[ÚÛÛÙ\Ü\^J	ÛY\ÜØYÙIÈO×Ê	ö+¶-ö)öã6)öava¶ã6*¶ãË	ÛÛ[YIÈ
H
KÈ
NÂB_BBIÙ\XÙWÚYH\ÜÙ]
	ÔÔÕÉÜÙ\XÙWÚY	×H
HÈ
[
H	ÔÔÕÉÜÙ\XÙWÚY	×HÂBIÛÝÝÈH\ÜÙ]
	ÔÔÕÉÜÛÝÝÉ×H
HÈ
[
H	ÔÔÕÉÜÛÝÝÉ×HÂBI[YHH\ÜÙ]
	ÔÔÕÉØÝ\ÝÛY\Û[YI×H
HÈØ[]^WÝ^ÙY[
ÜÝ[Û\Ú
	ÔÔÕÉØÝ\ÝÛY\Û[YI×H
H
H	ÉÎÂBIÛHH\ÜÙ]
	ÔÔÕÉØÝ\ÝÛY\ÜÛI×H
HÈØ[]^WÝ^ÙY[
ÜÝ[Û\Ú
	ÔÔÕÉØÝ\ÝÛY\ÜÛI×H
H
H	ÉÎÂBI[XZ[H\ÜÙ]
	ÔÔÕÉØÝ\ÝÛY\Ù[XZ[	×H
HÈØ[]^WÙ[XZ[
ÜÝ[Û\Ú
	ÔÔÕÉØÝ\ÝÛY\Ù[XZ[	×H
H
H	ÉÎÂBIÝ\ÈH\ÜÙ]
	ÔÔÕÉÛÝ\É×H
HÈØ[]^WÝ^\XWÙY[
ÜÝ[Û\Ú
	ÔÔÕÉÛÝ\É×H
H
H	ÉÎÂBKËÈ[Y]H\]Z\YY[ËBZY
	Ù\XÙWÚYH	ÛÝÝÈ[YJ
H	ÉÈOOH	[YH	ÉÈOOH	ÛH
HÂBB]ÜÜÙ[ÚÛÛÙ\Ü\^J	ÛY\ÜØYÙIÈOO×Ê	ö)ö,va6)ö.v)ö*6a¶)ö`¶-H6ã6)È6a¶)öav.v*¶*6,KË	ÛÛ[YIÈ
H
K
NÂB_BBZY
	ÉÈOOH	[XZ[	H\×Ù[XZ[
	[XZ[
H
HÂBB]ÜÜÙ[ÚÛÛÙ\Ü\^J	ÛY\ÜØYÙIÈO×Ê	ö)öã6avã6a6a¶)öav.v*¶*6,KË	ÛÛ[YIÈ
H
K
NÂB_BBYÛØ[	ÜÂBIXWÜÙ\XÙ\ÈHÛ[YWÑ]X\ÙN[Ý[ÙJ
KOÙ]ÝXJ	ÜÙ\XÙ\ÉÈ
NÂBIXWØ\Ú[Y[ÈHÛ[YWÑ]X\ÙN[Ý[ÙJ
KOÙ]ÝXJ	ÜØ\Ú[Y[ÉÈ
NÂBKËÈ]ÚÙ\XÙHÈÛÛ\]H\][Û[[Ý\H]^\ÝËØXÝ]KBIÙ\XÙHH	ÜOÙ]ÜÝÊBBIÜO\\JBBBKËÈÜÎYÛÜHÛÜ\ÜË\\YÔS[\Û]YÝ\\YBBBHÑSPÕY\][ÛXÙK\×ØXÝ]HÓHÉXWÜÙ\XÙ\ßHÒTHYH	YBBBIÙ\XÙWÚYBBJKBBPTVWÐBBJNÂBZY
H	Ù\XÙH
[
H	Ù\XÙVÉÚ\×ØXÝ]I×HOOHH
HÂBB]ÜÜÙ[ÚÛÛÙ\Ü\^J	ÛY\ÜØYÙIÈOO×Ê	ö,ö,vb6ã6,È6a¶)öav.v*¶*6,KË	ÛÛ[YIÈ
H
K
NÂB_BBI\][ÛÛZ[H
[
H	Ù\XÙVÉÝ[YI×NÂBIÝ\ÙHÛY]J	ÖK[KYNÉË	ÛÝÝÈ
NÂBI[ÙHÛY]J	ÖK[KYNÉË	ÛÝÝÈ
È
	\][ÛÛZ[
RSUWÒSÔÑPÓÓÈ
H
NÂBKËÈ[Ù\8 %[HÛHSTUQH
Ù\XÙWÚYÝ\Ý[YJHÈ][ÝXKXÛÚÚ[ËBI[Ù\YH	ÜO[Ù\
BBIXWØ\Ú[Y[ËBBX\^JBBBIÜÙ\XÙWÚY	ÈOO	Ù\XÙWÚYBBBIØÝ\ÝÛY\Û[YIÈOO	[YKBBBIØÝ\ÝÛY\ÜÛIÈOO	ÛKBBBIØÝ\ÝÛY\Ù[XZ[	ÈOO	[XZ[BBBIÜÝ\Ý[YIÈO	Ý\ÙBBBIÙ[Ý[YIÈOO	[ÙBBBIÜÝ]\ÉÈO	Ü[[ÉËBBBIÜ^[Y[ÜÝ]\ÉÈO	Ý[ZY	ËBBBIÛÝ\ÉÈOO	Ý\ËBBJKBBX\^J	ÉY	Ë	É\ÉË	É\ÉË	É\ÉË	É\ÉË	É\ÉË	É\ÉË	É\ÉË	É\ÉÈ
BBJNÂBZY
[ÙHOOH	[Ù\Y
HÂBBKËÈZÙ[HHSTUQHÛÛÝZ[[Û][Û
ÛÝZÙ[HÜ\ÜBB]ÜÜÙ[ÚÛÛÙ\Ü\^J	ÛY\ÜØYÙIÈOO×Ê	ö)öã6a6,¶av)öa6`¶*6a6)öbÈ6,v,¶,vb6-6+öaÈ6)ö,ö*Ë	ÛÛ[YIÈ
H
KH
NÂB_BBI\Ú[Y[ÚYH
[
H	ÜO[Ù\ÚYÂBIXÙHH
Ø]
H	Ù\XÙVÉÜXÙI×NÂBIØ[[\HÛ[YWÐØ[[\Ñ[Ú[N[Ý[ÙJ
NÂBIÝ[[X\HH\^JBBIÚY	ÈO	\Ú[Y[ÚYBBIÙ]IÈO	Ø[[\OÜX]Ú[[J	ÛÝÝË	Ú]IÈ
KBBIÜXÙIÈO	Ø[[\O×Ü\ÚX[ÙYÚ]Ê
Ý[ÊH	XÙH
KBJNÂBKËÈKKH^[Y[Ý][ÈKKBBZY
	XÙH
HÂBBI[\HÛ[YWÔ^[Y[Ò[\[Ý[ÙJ
NÂBBI\Ý[H	[\OØÙ\Ü×Ü^[Y[
	\Ú[Y[ÚY	XÙH
NÂBBZY
\×ÝÜÙ\Ü	\Ý[
H
HÂBBBKËÈ^[Y[Ø]]Ø^H\Ü8¡%\Ú[Y[[XZ[È[[Ë[ÜH\Ù\BBB]ÜÜÙ[ÚÛÛÙ\Ü\^JBBBBIÛY\ÜØYÙIÈO	\Ý[OÙ]Ù\ÜÛY\ÜØYÙJ
KBBBJKL
NÂBB_BBBKËÈ]\Y\XÝTÛÈHÝÜÙ\Ø[ÛÈÈHØ]]Ø^KBB]ÜÜÙ[ÚÛÛÜÝXØÙ\ÜÊ\^JBBBIÛY\ÜØYÙIÈOO×Ê	ö+ö,H6+v)öa6)öa¶*¶`¶)öa6*6aÈ6+ö,v«ö)öaÈ6o¶,v+ö)ö+¶*Ë	ÛÛ[YIÈ
KBBBIÜY\XÝÝ\	ÈOO	\Ý[BBBIØ\Ú[Y[	ÈOO	Ý[[X\KBBJH
NÂB_BBKËÈYHÙ\XÙH
XÙHH
H8¡%È^[Y[YYYB]ÜÜÙ[ÚÛÛÜÝXØÙ\ÜÊ\^JBBBIÛY\ÜØYÙIÈOOH×Ê	öa¶b6*6*6-6av)È6*ö*6*6-6+È6aÈ6+ö,6)öa¶*¶.6)ö,H6*¶(öã6ã6+È6)ö,ö*Ë	ÛÛ[YIÈ
KBBBIØ\Ú[Y[	ÈOO	Ý[[X\KBJH
NÂ_BB