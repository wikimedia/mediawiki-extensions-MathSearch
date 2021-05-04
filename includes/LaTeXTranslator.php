<?php

class LaTeXTranslator {

	/** @var string[][] */
	private $replacements;

	/**
	 * @var string contains frequently occurring regex for recurisve extraction of nested parenthesis
	 */
	private $par = "(\\{(?>[^{}]|(?-1))*\\})";
	/**
	 * @var string contains frequently occurring regex for single argument of number or Greek letter
	 */
	private $arg = "(\\\\[?[A-z]*\\]?|[0-9]|[A-z]{1})";

	function __construct() {
		# reference: http://stackoverflow.com/a/28611214/4521584
		$this->replacements = [
			[
				# Trignometric & Trig Integrals: \acosh@{x}
				'search' =>
					"~\\\\(a?(?>cos|sin|tan|csc|cot|sec)h?)(int)?@{0,2}(" . $this->arg . "|" .
					$this->par . ")~i",
				# Trignometric: ArcCosh[x]
				'replace' => static function ( array $m ) {
					return ( $m[1][0] == 'a' ? 'Arc' . ucfirst( strtolower( substr( $m[1], 1 ) ) )
						: ucfirst( strtolower( $m[1] ) ) ) .
							( strlen( $m[2] ) > 0 ? 'Integral' : '' ) . '[' .
							LaTeXTranslator::brackR( $m[3] ) . ']';
				}
			],
			[
				# Logarithm
				'search' =>
					"~\\\\(?>log|ln)b?" . $this->par . "?@{0,2}(" . $this->arg . "|" . $this->par .
					")~i",
				# Logarithm: Log[x]
				'replace' => static function ( array $m ) {
					return 'Log[' . ( strlen( $m[1] ) > 0 ? $m[1] . ',' : '' ) .
						LaTeXTranslator::brackR( $m[2] ) . ']';
				}
			],
			[
				# Airy: \AiryAi@{z}
				'search' => "~\\\\Airy([B|A]i)@{0,2}(" . $this->arg . "|" . $this->par . ")~i",
				# Airy: AiryAi[z]
				'replace' => static function ( array $m ) {
					return 'Airy' . $m[1] . '[' . LaTeXTranslator::brackR( $m[2] ) . ']';
				}
			],
			[
				# Airy Modulus M: \AiryModulusM@{z}
				'search' => "~\\\\AiryModulusM@{0,2}(" . $this->arg . "|" . $this->par . ")~i",
				# Airy Modulus M: Sqrt[AiryAi[x]^2+AiryBi[x]^2]
				'replace' => static function ( array $m ) {
					return 'Sqrt[AiryAi[' . LaTeXTranslator::brackR( $m[1] ) . ']^2+AiryBi[' .
						LaTeXTranslator::brackR( $m[1] ) . ']^2]';
				}
			],
			[
				# Arithmetic Geometric Mean: \AGM@{a}{g}
				'search' =>
					"~\\\\AGM@{0,2}(" . $this->arg . "|" . $this->par . ")(" . $this->arg . "|" .
					$this->par . ")~i",
				# Arithmetic Geometric Mean: ArithmeticGeometricMean[a,b]
				'replace' => static function ( array $m ) {
					return 'ArithmeticGeometricMean[' . LaTeXTranslator::brackR( $m[1] ) . ',' .
						LaTeXTranslator::brackR( $m[4] ) . ']';
				}
			],
			[
				# Anger: \AngerJ{\nu}@{z}
				'search' => "~\\\\AngerJ" . $this->par . "@{0,2}(" . $this->arg . "|" . $this->par .
							")~i",
				# Anger: AngerJ[\nu,z]
				'replace' => static function ( array $m ) {
					return 'AngerJ[' . LaTeXTranslator::brackR( $m[1] ) . ',' . LaTeXTranslator::brackR( $m[2] ) . ']';
				}
			],
			[
				# Appell: \AppellFi@{\alpha}{\beta}{\beta'}{\gamma}{x}{y}
				'search' => "~\\\\AppellF[i{1,3}v?]@{0,2}(" . $this->arg . "|" . $this->par . ")(" .
							$this->arg . "|" . $this->par . ")(" . $this->arg . "|" . $this->par .
							")(" . $this->arg . "|" . $this->par . ")(" . $this->arg . "|" .
							$this->par . ")(" . $this->arg . "|" . $this->par . ")~i",
				# Appell: AppellF1[\alpha,\beta,\beta',\gamma,x,y]
				'replace' => static function ( array $m ) {
					return 'AppellF1[' .
						LaTeXTranslator::brackR( $m[1] ) . ',' .
						LaTeXTranslator::brackR( $m[4] ) . ',' .
						LaTeXTranslator::brackR( $m[7] ) . ',' .
						LaTeXTranslator::brackR( $m[10] ) . ',' .
						LaTeXTranslator::brackR( $m[13] ) . ',' .
						LaTeXTranslator::brackR( $m['16'] ) . ']';
				}
			],
			[
				# Barnes G: \BarnesGamma@{z}
				'search' => "~\\\\BarnesGamma@{0,2}(" . $this->arg . "|" . $this->par . ")~i",
				# Barnes G: BarnesG[z]
				'replace' => static function ( array $m ) {
					return 'BarnesG[' . LaTeXTranslator::brackR( $m[1] ) . ']';
				}
			],
			[
				# BesselJ: \BesselJ{n}@{z}  - default?? for BesselJ{nu}/Cheby w/ no z???
				'search' => "~\\\\Bessel([A-Z])" . $this->par . "@?(" . $this->par . ")?~i",
				# BesselJ: BesselJ[n,z]
				'replace' => static function ( array $m ) {
					return 'Bessel' . $m[1] . '[' . LaTeXTranslator::brackR( $m[2] ) .
						( strlen( $m[3] ) > 0 ? ',' . LaTeXTranslator::brackR( $m[3] ) : ',0' ) . ']';
				}
			],
			[
				# Binomial Coefficient: \binomial{m}{n}
				'search' => "~\\\\binom(?>ial)?@{0,2}" . $this->par . $this->par . "~i",
				# Binomial Coefficient: Binomial[n,m]
				'replace' => static function ( array $m ) {
					return 'Binomial[' . LaTeXTranslator::brackR( $m[2] ) . ',' . LaTeXTranslator::brackR( $m[1] ) .
						']';
				}
			],
			[
				# Catalan Number: \CatalanNumber@{n}
				'search' => "~\\\\CatalanNumber@{0,2}(" . $this->arg . "|" . $this->par . ")~i",
				# Catalan Number: CatalanNumber[n]
				'replace' => static function ( array $m ) {
					return 'CatalanNumber[' . LaTeXTranslator::brackR( $m[1] ) . ']';
				}
			],
			[
				# Ceiling: \ceiling{x}
				'search' => "~\\\\ceiling@{0,2}(" . $this->arg . "|" . $this->par . ")~i",
				# Ceiling: Ceiling[x]
				'replace' => static function ( array $m ) {
					return 'Ceiling[' . LaTeXTranslator::brackR( $m[1] ) . ']';
				}
			],
			[
				# Chebyshev: \ChebyT{x}@{n}
				'search' => "~\\\Cheby([A-Z])" . $this->par . "@?(" . $this->par . ")?~i",
				# Chebyshev: ChebyshevT[n,x]
				'replace' => static function ( array $m ) {
					return 'Chebyshev' . $m[1] . '[' . LaTeXTranslator::brackR( $m[2] ) .
						( strlen( $m[3] ) > 0 ? ',' . LaTeXTranslator::brackR( $m[3] ) : ',0' ) . ']';
				}
			],
			[
				# Complex Conjugate: \conj{z}
				'search' => "~\\\\conj@{0,2}" . $this->par . "~i",
				# Complex Conjugate: Conjugate[z]
				'replace' => static function ( array $m ) {
					return 'Conjugate[' . LaTeXTranslator::brackR( $m[1] ) . ']';
				}
			],
			[
				# Cylinder: \Cylinder{\nu}@{z}
				'search' =>
					"~\\\\Cylinder" . $this->par . "@{0,2}(" . $this->arg . "|" . $this->par .
					")~i",
				# Cylinder: ParabolicCylinderD[\nu,z]
				'replace' => static function ( array $m ) {
					return 'ParabolicCylinderD[' . LaTeXTranslator::brackR( $m[1] ) . ',' .
						LaTeXTranslator::brackR( $m[2] ) . ']';
				}
			],
			[
				# Dawson's Integral: \DawsonsInt@{z}
				'search' => "~\\\DawsonsInt@{0,2}(" . $this->arg . "|" . $this->par . ")~i",
				# Dawsons Integral: DawsonF[z]
				'replace' => static function ( array $m ) {
					return 'DawsonF[' . LaTeXTranslator::brackR( $m[1] ) . ']';
				}
			],
			[
				# Dedekind's Eta: \DedekindModularEta@{\tau}
				'search' => "~\\\\DedekindModularEta@{0,2}(" . $this->arg . "|" . $this->par . ")~i",
				# Dedekind's Eta: DedekindEta[\tau]
				'replace' => static function ( array $m ) {
					return 'DedekindEta[' . LaTeXTranslator::brackR( $m[1] ) . ']';
				}
			],
			[
				# Derivative: \deriv{f}{x}
				'search' => "~\\\\p?deriv@{0,2}" . $this->par . $this->par . "~i",
				# Derivative: D[f,x]
				'replace' => static function ( array $m ) {
					return 'D[' . LaTeXTranslator::brackR( $m[1] ) . ',' . LaTeXTranslator::brackR( $m[2] ) . ']';
				}
			],
			[
				# Determinant: \det
				'search' => "~\\\\det@{0,2}(" . $this->arg . "|" . $this->par . ")~i",
				# Determinant: Det[a]
				'replace' => static function ( array $m ) {
					return 'Det[' . $m[1] . ']';
				}
			],
			[
				# Divergence: \divergence
				'search' => "~\\\\divergence@{0,2}(" . $this->arg . "|" . $this->par . ")~i",
				# Divergence: divergence
				'replace' => static function ( array $m ) {
					return 'Div[' . $m[1] . ']';
				}
			],
			[
				# Elliptic Integral: \CompEllIntC@{a}
				'search' =>
					"~\\\\(?>Comp)?EllInt[C]?([A-Z]|Pi)@{0,3}(" . $this->arg . "|" . $this->par .
					")(" . $this->arg . "|" . $this->par . ")?~i",
				# Elliptic Integral: EllipticC[x, Sqrt[m]]
				'replace' => static function ( array $m ) {
					return 'Elliptic' . $m[1] . '[' . ( count( $m ) > 5 ?
						LaTeXTranslator::brackR( $m[2] ) . ', Sqrt[' . LaTeXTranslator::brackR( $m[4] ) . ']'
						: 'Sqrt[' . LaTeXTranslator::brackR( $m[2] ) . ']' ) . ']';
				}
			],
			[
				# Error: \erf@{z}
				'search' => "~\\\\erf[a-z]?@{0,2}(" . $this->arg . "|" . $this->par . ")~i",
				# Error: Erf[z]
				'replace' => static function ( array $m ) {
					return 'Erf[' . LaTeXTranslator::brackR( $m[1] ) . ']';
				}
			],
			[
				# Euler Beta & Gamma: \EulerBeta@{a}{b}
				'search' => "~\\\\Euler(Beta|Gamma)@{0,2}(" . $this->arg . "|" . $this->par . ")(" .
							$this->arg . "|" . $this->par . ")?~i",
				# Euler Beta & Gamma: Beta[a,b]
				'replace' => static function ( array $m ) {
					return $m[1] . '[' . LaTeXTranslator::brackR( $m[2] ) .
						( strlen( $m[5] ) > 0 && strtolower( $m[1] ) == 'beta' ?
							',' . LaTeXTranslator::brackR( $m[5] ) : '' ) . ']';
				}
			],
			[
				# Exponential: \exp@{x}
				'search' => "~\\\\exp@{0,2}(" . $this->arg . "|" . $this->par . ")~i",
				# Exponential: Exp[x]
				'replace' => static function ( array $m ) {
					return 'Exp[' . LaTeXTranslator::brackR( $m[1] ) . ']';
				}
			],
			[
				# Exponential Integral: \ExpInti@{x}
				'search' => "~\\\\ExpInt([a-z])?" . $this->par . "?@{0,2}(" . $this->arg . "|" .
							$this->par . ")~i",
				# Exponential Integral: ExpIntegralEi[z]
				'replace' => static function ( array $m ) {
					return 'ExpIntegralE' . ( $m[1] == 'i' ? 'i' : '' ) . '[' .
						( strlen( $m[2] ) > 0 ? LaTeXTranslator::brackR( $m[2] ) . ',' : '' ) .
						LaTeXTranslator::brackR( $m[3] ) . ']';
				}
			],
			[
				# Floor: \floor{x}
				'search' => "~\\\\floor(" . $this->arg . "|" . $this->par . ")~i",
				# Floor: Floor[x]
				'replace' => static function ( array $m ) {
					return 'Floor[' . LaTeXTranslator::brackR( $m[1] ) . ']';
				}
			],
			[
				# Fraction: \frac{a}{b}
				'search' => "~\\\\frac(" . $this->arg . "|" . $this->par . ")(" . $this->arg . "|" .
							$this->par . ")~i",
				# Fraction: a/b
				'replace' => static function ( array $m ) {
					return ( strlen( $m[3] ) > 0 ? '(' . LaTeXTranslator::brackR( $m[1] ) . ')'
						: LaTeXTranslator::brackR( $m[1] ) ) . '/' .
							( count( $m ) > 6 ? '(' . LaTeXTranslator::brackR( $m[4] ) . ')'
								: LaTeXTranslator::brackR( $m[4] ) );
				}
			],
			[
				# Fresnel: \FresnelSin@{z}
				'search' => "~\\\\Fresnel([a-z]*)@{0,2}(" . $this->arg . "|" . $this->par . ")~i",
				# Fresnel: FresnelS[z]
				'replace' => static function ( array $m ) {
					return 'Fresnel' . ucfirst( $m[1][0] ) . '[' . LaTeXTranslator::brackR( $m[2] ) . ']';
				}
			],
			[
				# Greek Letter: \Alpha
				'search' => "~\\\\(alpha|beta|gamma|delta|epsilon|varepsilon|zeta|eta|theta|"
					. "vartheta|gamma|kappa|lambda|mu|nu|xi|o[^mega]|pi|varpi|rho|varrho|sigma|"
					. "varsigma|tau|upsilon|phi|varphi|chi|psi|omega)~i",
				# Greek Letter: \[CapitalAlpha]
				'replace' => static function ( array $m ) {
					return '\\[' . ( strtolower( $m[1] ) != $m[1] ? 'Capital' : '' ) .
						ucfirst( $m[1] ) . ']';
				}
			],
			[
				# Gamma: \GammaP@{a}{z}
				'search' =>
					"~\\\\Gamma(?>[PQ])@?(" . $this->arg . "|" . $this->par . ")(" . $this->arg .
					"|" . $this->par . ")~i",
				# Gamma (Incomplete):
				'replace' => static function ( array $m ) {
					return 'Gamma[' . LaTeXTranslator::brackR( $m[1] ) . ',' . LaTeXTranslator::brackR( $m[4] ) . ']';
				}
			],
			[
				# Gudermannian: \Gudermannian@{x}
				'search' => "~\\\\(arc)?Gudermannian@{0,2}(" . $this->arg . "|" . $this->par .
							")~i",
				# Gudermannian: Gudermannian[z]
				'replace' => static function ( array $m ) {
					return ( strlen( $m[1] ) > 0 ? 'Inverse' : '' ) . 'Gudermannian[' .
						LaTeXTranslator::brackR( $m[2] ) . ']';
				}
			],
			[
				# Generalized Hermite: \GenHermiteH{n}@{x}
				'search' =>
					"~\\\\GenHermiteH" . $this->par . "@{0,2}(" . $this->arg . "|" . $this->par .
					")~i",
				# Hermite: HermiteH[n,x]
				'replace' => static function ( array $m ) {
					return 'HermiteH[' . LaTeXTranslator::brackR( $m[1] ) . ',' . LaTeXTranslator::brackR( $m[2] ) .
						']';
				}
			],
			[
				# HurwitzZeta: \HurwitzZeta@{s}{a}
				'search' =>
					"~\\\\HurwitzZeta@?(" . $this->arg . "|" . $this->par . ")(" . $this->arg .
					"|" . $this->par . ")~i",
				# HurwitzZeta: HurwitzZeta[s,a]
				'replace' => static function ( array $m ) {
					return 'HurwitzZeta[' . LaTeXTranslator::brackR( $m[1] ) . ',' . LaTeXTranslator::brackR( $m[4] ) .
						']';
				}
			],
			[
				# Hypergeometric: \HypergeoF@{a}{b}{c}{d}
				'search' =>
					"~\\\\HypergeoF(@{0,3})" . $this->par . $this->par . $this->par . $this->par .
					"~i",
				# Hypergeometric: Hypergeometric2F1[a,b,c,d]
				'replace' => static function ( array $m ) {
					return 'Hypergeometric2F1[' . $m[2] . ',' . $m[3] . ',' . $m[4] . ',' . $m[5] .
						']';
				}
			],
			[
				# Hypergeometric (Generalized): \HyperpFq{p}{q}@{{\bf a}}{{\bf b}}{z}
				'search' =>
					"~\\\\HyperpFq(" . $this->arg . "|" . $this->par . ")(" . $this->arg . "|" .
					$this->par . ")@{0,3}(" . $this->arg . "|" . $this->par . ")(" . $this->arg .
					"|" . $this->par . ")(" . $this->arg . "|" . $this->par . ")~i",
				# Hypergeometric (Generalized): HypergeometricPFQ[a,b,c]
				'replace' => static function ( array $m ) {
					return 'HypergeometricPFQ[' . $m[7] . ',' . $m[10] . ',' .
						LaTeXTranslator::brackR( $m[13] ) . ']';
				}
			],
			[
				# Incomplete Beta & Gamma: \IncBeta{x}@{a}{b}, \IncGamma@{a}{z}
				'search' => "~\\\\Inc(Beta|Gamma)" . $this->par . "?@{0,2}(" . $this->arg . "|" .
							$this->par . ")(" . $this->arg . "|" . $this->par . ")~i",
				# Incomplete Beta & Gamma: Beta[z,a,b], Gamma[a,z]
				'replace' => static function ( array $m ) {
					return $m[1] . '[' .
						( strlen( $m[2] ) > 0 ? LaTeXTranslator::brackR( $m[2] ) . ',' : '' ) .
						LaTeXTranslator::brackR( $m[3] ) . ',' .
						LaTeXTranslator::brackR( $m[6] ) . ']';
				}
			],
			[
				# Inverse Error (including complementary): \inverfc@{x}
				'search' => "~\\\\inverf(c)?@{0,2}(" . $this->arg . "|" . $this->par . ")~i",
				# Inverse Error (including complementary): InverseErfc[x]
				'replace' => static function ( array $m ) {
					return 'InverseErf' . $m[1] . '[' . LaTeXTranslator::brackR( $m[2] ) . ']';
				}
			],
			[
				# Imaginary Unit: \iunit
				'search' => "~\\\\iunit~i",
				# Imaginary Unit: I return 'I';}
				'replace' => static function () {
					return 'I';
				}
			],
			[
				# Jacobi Elliptics: \Jacobisd@{z}{k}
				'search' =>
					"~\\\\(arc)?Jacobi(Zeta|[a-z]{2})@{0,2}(" . $this->arg . "|" . $this->par .
					")(" . $this->arg . "|" . $this->par . ")~i",
				# Jacobi Elliptics: JacobiSD[u,m]
				'replace' => static function ( array $m ) {
					return ( strlen( $m[1] ) > 0 ? 'Inverse' : '' ) . 'Jacobi' .
						( strlen( $m[2] ) == 2 ? strtoupper( $m[2] ) : 'Zeta' ) . '[' .
						LaTeXTranslator::brackR( $m[3] ) . ', Sqrt[' . LaTeXTranslator::brackR( $m[6] ) . ']]';
				}
			],
			[
				# Jacobi Polynomials: \JacobiP{\alpha}{\beta}{n}@{x}
				'search' =>
					"~\\\\JacobiP" . $this->par . $this->par . $this->par . "@{0,2}(" . $this->arg .
					"|" . $this->par . ")~i",
				# Jacobi Polynomial: JacobiP[n,a,b,x]
				'replace' => static function ( array $m ) {
					return 'JacobiP[' .
						LaTeXTranslator::brackR( $m[2] ) . ',' .
						LaTeXTranslator::brackR( $m[1] ) . ',' .
						LaTeXTranslator::brackR( $m[3] ) . ',' .
						LaTeXTranslator::brackR( $m[4] ) . ']';
				}
			],
			[
				# Kelvin: \Kelvinber{\nu}@{x}
				'search' => "~\\\\Kelvin([bk]e[ri])(" . $this->par . ")@{0,2}(" . $this->arg . "|" .
							$this->par . ")?~i",
				# Kelvin: KelvinBer[n,z]
				'replace' => static function ( array $m ) {
					return 'Kelvin' . ucfirst( $m[1] ) . '[' . LaTeXTranslator::brackR( $m[2] ) .
						( strlen( LaTeXTranslator::brackR( $m[4] ) ) > 0 ? ',' . LaTeXTranslator::brackR( $m[4] )
							: '' ) . ']';
				}
			],
			[
				# Klein Invariant: \ModularJ@{\tau}
				'search' => "~\\\\ModularJ" . $this->par . "~i",
				# Klein Invariant: KleinInvariantJ[\tau]
				'replace' => static function ( array $m ) {
					return 'KleinInvariantJ[' . LaTeXTranslator::brackR( $m[1] ) . ']';
				}
			],
			[
				# Kronecker: \Kronecker{j}{k}
				'search' => "~\\\\Kronecker" . $this->par . $this->par . "~i",
				# Kronecker: Kronecker[j,k]
				'replace' => static function ( array $m ) {
					return 'KroneckerDelta[' .
						LaTeXTranslator::brackR( $m[1] ) . ',' .
						LaTeXTranslator::brackR( $m[2] ) . ']';
				}
			],
			[
				# LaguerreL[\a]{n}@{x}
				'search' =>
					"~\\\\LaguerreL(\\[.*\\])?" . $this->par . "@?(" . $this->arg . "|" . $this->par .
					")~i",
				# LaguerreL: LaguerreL[n,a,x]
				'replace' => static function ( array $m ) {
					return 'LaguerreL[' . LaTeXTranslator::brackR( $m[2] ) . ',' .
						( strlen( LaTeXTranslator::brackR( $m[1] ) ) > 0 ? LaTeXTranslator::brackR( $m[1] ) . ','
							: '' ) . LaTeXTranslator::brackR( $m[3] ) . ']';
				}
			],
			[
				# Legendre/Ferrers: \LegendreP[\mu]{\nu}@{x} | \FerrersP[\mu]{\nu}@{x}
				'search' =>
					"~\\\\(?>Legendre|Ferrers)([PQ]|Poly)(\\[(?>[^{}]|(?-1))*\\])?" . $this->par .
					"@?(" . $this->arg . "|" . $this->par . ")?~i",
				# Legendre/Ferrers: LegendreP[\nu,x] | LegendreP[\nu,\mu,x]
				'replace' => static function ( array $m ) {
					return 'Legendre' . $m[1] . '[' . LaTeXTranslator::brackR( $m[3] ) . ',' .
						( strlen( LaTeXTranslator::brackR( $m[2] ) ) > 0 ? LaTeXTranslator::brackR( $m[2] ) . ','
							: '' ) . LaTeXTranslator::brackR( $m[5] ) . ']';
				}
			],
			[
				# LerchPhi: \LerchPhi@{z}{s}{a}
				'search' =>
					"~\\\\LerchPhi@{0,2}(" . $this->arg . "|" . $this->par . ")(" . $this->arg .
					"|" . $this->par . ")(" . $this->arg . "|" . $this->par . ")~i",
				# LerchPhi: LerchPhi[z,s,a]
				'replace' => static function ( array $m ) {
					return 'LerchPhi[' .
						LaTeXTranslator::brackR( $m[1] ) . ',' .
						LaTeXTranslator::brackR( $m[4] ) . ',' .
						LaTeXTranslator::brackR( $m[7] ) . ']';
				}
			],
			[
				# Log Integral: \LogInt@{x}
				'search' => "~\\\\LogInt@{0,2}(" . $this->arg . "|" . $this->par . ")~i",
				# Log Integral: LogIntegral[x]
				'replace' => static function ( array $m ) {
					return 'LogIntegral[' . LaTeXTranslator::brackR( $m[1] ) . ']';
				}
			],
			[
				# Mittag-Leffler: \MittagLeffler{a}{b}@{z}
				'search' =>
					"~\\\\MittagLeffler" . $this->par . $this->par . "@{0,2}(" . $this->arg . "|" .
					$this->par . ")~i",
				# Mittag-Leffler: MittagLefflerE[\alpha,\beta,z]
				'replace' => static function ( array $m ) {
					return 'MittagLefflerE[' .
						LaTeXTranslator::brackR( $m[1] ) . ',' .
						LaTeXTranslator::brackR( $m[2] ) . ',' .
						LaTeXTranslator::brackR( $m[3] ) . ']';
				}
			],
			[
				# Modulus: n \mod m
				'search' =>
					"~(" . $this->arg . "|" . $this->par . ")\\\\mod@{0,2}(" . $this->arg . "|" .
					$this->par . ")~i",
				# Modulus: Mod[m,n]
				'replace' => static function ( array $m ) {
					return 'Mod[' . LaTeXTranslator::brackR( $m[1] ) . ',' . LaTeXTranslator::brackR( $m[4] ) . ']';
				}
			],
			[
				# Permutations: \Permutations{n}
				'search' => "~\\\\Permutations@{0,2}(" . $this->arg . "|" . $this->par . ")~i",
				# Permutations: Permutations[n]
				'replace' => static function ( array $m ) {
					return 'Permutations[' . LaTeXTranslator::brackR( $m[1] ) . ']';
				}
			],
			[
				# Pochhammer: \pochammer{a}{n}
				'search' => "~\\\\pochhammer@{0,2}" . $this->par . $this->par . "~i",
				# Pochhammer: Pochhammer[a,n]
				'replace' => static function ( array $m ) {
					return 'Pochhammer[' . LaTeXTranslator::brackR( $m[1] ) . ',' . LaTeXTranslator::brackR( $m[2] ) .
						']';
				}
			],
			[
				# PolyGamma: \polygamma{n}@{z}
				'search' =>
					"~\\\\Polygamma" . $this->par . "@{0,2}(" . $this->arg . "|" . $this->par .
					")~i",
				# PolyGamma: PolyGamma[n,z]
				'replace' => static function ( array $m ) {
					return 'PolyLog[' . LaTeXTranslator::brackR( $m[1] ) . ',' . LaTeXTranslator::brackR( $m[2] ) . ']';
				}
			],
			[
				# PolyLog: \Polylogarithms{x}@{z}
				'search' =>
					"~\\\\Polylogarithm" . $this->par . "@{0,2}(" . $this->arg . "|" . $this->par .
					")~i",
				# PolyLog: PolyLog[x,z]
				'replace' => static function ( array $m ) {
					return 'PolyLog[' . LaTeXTranslator::brackR( $m[1] ) . ',' . LaTeXTranslator::brackR( $m[2] ) . ']';
				}
			],
			[
				# Q Factorial: \qFactorial{a}{q}{n}
				'search' => "~\\\\qFactorial@{0,2}" . $this->par . $this->par . $this->par . "~i",
				# Q Factorial: QFactorial[a,q,n]
				'replace' => static function ( array $m ) {
					return 'QFactorial[' . LaTeXTranslator::brackR( $m[2] ) . ',' . LaTeXTranslator::brackR( $m[3] ) .
						']';
				}
			],
			[
				# Q Gamma: \qGamma{q}@{z}
				'search' => "~\\\\qGamma" . $this->par . "@{0,2}(" . $this->arg . "|" . $this->par .
					")~i",
				# Q Gamma: QGamma[z,q]
				'replace' => static function ( array $m ) {
					return 'QGamma[' . LaTeXTranslator::brackR( $m[2] ) . ',' . LaTeXTranslator::brackR( $m[1] ) . ']';
				}
			],
			[
				# Ramanujan Tau: \RamanujanTau@{k}
				'search' => "~\\\\RamanujanTau@{0,2}(" . $this->arg . "|" . $this->par . ")~i",
				# Ramanujan Tau: RamanujanTau[k]
				'replace' => static function ( array $m ) {
					return 'RamanujanTau[' . LaTeXTranslator::brackR( $m[1] ) . ']';
				}
			],
			[
				# Real Part: \realpart{z}
				'search' => "~\\\\realpart@{0,2}" . $this->par . "~i",
				# Real Part: Re[z]
				'replace' => static function ( array $m ) {
					return 'Re[' . LaTeXTranslator::brackR( $m[1] ) . ']';
				}
			],
			[
				# Riemann: \RiemannXi@{s}
				'search' => "~\\\\Riemann(Xi)@{0,2}(" . $this->arg . "|" . $this->par . ")~i",
				# Riemann: RiemannXi[s]
				'replace' => static function ( array $m ) {
					return 'Riemann' . $m[1] . '[' . LaTeXTranslator::brackR( $m[2] ) . ']';
				}
			],
			[
				# Scorer: \ScorerHi@{z}
				'search' => "~\\\\Scorer([G|H]i)@{0,2}(" . $this->arg . "|" . $this->par . ")~i",
				# Scorer: ScorerHi[z]
				'replace' => static function ( array $m ) {
					return 'Scorer' . $m[1] . '[' . LaTeXTranslator::brackR( $m[2] ) . ']';
				}
			],
			[
				# Sign: \sign@{x}
				'search' => "~\\\\sign@{0,2}(" . $this->arg . "|" . $this->par . ")~i",
				# Sign: Sign[z]
				'replace' => static function ( array $m ) {
					return 'Sign[' . LaTeXTranslator::brackR( $m[1] ) . ']';
				}
			],
			[
				# Sin Integral: \SinInt@{x}
				'search' => "~\\\\Sin(h?)Int@{0,2}(" . $this->arg . "|" . $this->par . ")~i",
				# Sin Integral: SinInt[z]
				'replace' => static function ( array $m ) {
					return 'Sin' . $m[1] . 'Integral[' . LaTeXTranslator::brackR( $m[2] ) . ']';
				}
			],
			[
				# Spherical BesselJ/Y | HankelH1/H2: \SphBesselJ{n}@{z}
				'search' =>
					"~\\\\Sph(Bessel|Hankel)(J|Y|Hii?)" . $this->par . "@{0,2}(" . $this->arg .
					"|" . $this->par . ")~i",
				# Spherical BesselJ/Y | HankelH1/H2: SphericalBesselJ[n,z]
				'replace' => static function ( array $m ) {
					return 'Spherical' . $m[1] . '' . ( substr( $m[2], 0, 2 ) !== 'Hi'
						? $m[2] : 'H' . ( strlen( $m[2] ) - 1 ) ) . '[' . LaTeXTranslator::brackR( $m[3] ) .
							',' . LaTeXTranslator::brackR( $m[4] ) . ']';
				}
			],
			[
				# Spherical Harmonic: \SphericalHarmonicY{l}{m}@{\theta}{\phi}
				'search' =>
					"~\\\\SphericalHarmonicY" . $this->par . $this->par . "@?(" . $this->arg . "|" .
					$this->par . ")?(" . $this->arg . "|" . $this->par . ")?~i",
				# Spherical Harmonic: SphericalHarmonicY[l,m,\theta,\phi]
				'replace' => static function ( array $m ) {
					return 'SphericalHarmonicY[' . LaTeXTranslator::brackR( $m[1] ) . ',' .
						LaTeXTranslator::brackR( $m[2] ) . ',' . LaTeXTranslator::brackR( $m[3] ) . ',' .
						LaTeXTranslator::brackR( $m[6] ) . ']';
				}
			],
			[
				# Spheroidal Eigenvalue: \SpheroidalEigenvalueLambda{m}{n}@{\gamma}
				'search' => "~\\\\SpheroidalEigenvalueLambda" . $this->par . $this->par . "@?(" .
							$this->arg . "|" . $this->par . ")?~i",
				# Spheroidal Eigenvalue: SpheroidalEigenvalue[n,m,\gamma]
				'replace' => static function ( array $m ) {
					return 'SpheroidalEigenvalue[' . LaTeXTranslator::brackR( $m[1] ) . ',' .
						LaTeXTranslator::brackR( $m[2] ) . ',' . LaTeXTranslator::brackR( $m[3] ) . ']';
				}
			],
			[
				# Spheroidal Ps: \SpheroidalPs{m}{n}@{z}{\gamma^2}
				'search' =>
					"~\\\\Spheroidal(P|Q)s" . $this->par . $this->par . "@?(" . $this->arg . "|" .
					$this->par . ")?(" . $this->arg . "|" . $this->par . ")?~i",
				# Spheroidal Ps: SpheroidalPs[n,m,\gamma,z]
				'replace' => static function ( array $m ) {
					return 'Spheroidal' . $m[1] . 'S[' . LaTeXTranslator::brackR( $m[2] ) . ',' .
						LaTeXTranslator::brackR( $m[3] ) . ',Sqrt[' . LaTeXTranslator::brackR( $m[7] ) . '],' .
						LaTeXTranslator::brackR( $m[4] ) . ']';
				}
			],
			[
				# Sqrt: \sqrt@{x}
				'search' => "~\\\\sqrt@{0,2}(" . $this->arg . "|" . $this->par . ")~i",
				# Sqrt: Sqrt[x]
				'replace' => static function ( array $m ) {
					return 'Sqrt[' . LaTeXTranslator::brackR( $m[1] ) . ']';
				}
			],
			[
				# StruveH: \StruveH{\nu}@{z}
				'search' =>
					"~\\\\Struve(H|L)" . $this->par . "@{0,2}(" . $this->arg . "|" . $this->par .
					")~i",
				# StruveH: StruveH[n,z]
				'replace' => static function ( array $m ) {
					return 'Struve' . $m[1] . '[' . LaTeXTranslator::brackR( $m[2] ) . ',' .
						LaTeXTranslator::brackR( $m[3] ) . ']';
				}
			],
			[
				# WeberE: \WeberE{\nu}@{z}
				'search' => "~\\\\WeberE" . $this->par . "@{0,2}(" . $this->arg . "|" . $this->par .
							")~i",
				# WeberE: WeberE[\nu,z]
				'replace' => static function ( array $m ) {
					return 'WeberE[' . LaTeXTranslator::brackR( $m[1] ) . ',' . LaTeXTranslator::brackR( $m[2] ) . ']';
				}
			],
			[
				# Whittaker: \WhittakerM{\kappa}{\mu}@{z}
				'search' =>
					"~\\\\Whit(M|W)" . $this->par . $this->par . "@{0,2}(" . $this->arg . "|" .
					$this->par . ")~i",
				# Whittaker: WhittakerW[k,m,z]
				'replace' => static function ( array $m ) {
					return 'Whittaker' . $m[1] . '[' . LaTeXTranslator::brackR( $m[2] ) . ',' .
						LaTeXTranslator::brackR( $m[3] ) . ',' . LaTeXTranslator::brackR( $m[4] ) . ']';
				}
			]
		];
	}

	/**
	 * @var string contains data in Wiki html input form
	 */

	/**
	 * Returns LaTeX arguments without curly brackets
	 * @param string $arg
	 * @return string
	 */
	public static function brackR( $arg ) {
		$arg = trim( $arg, " " );
		if ( substr( $arg, 0, 1 ) == "{" && substr( $arg,  -1 ) == "}" ) {
			$arg = substr( $arg, 1, strlen( $arg ) - 2 );
		}
		return $arg;
	}

	/**
	 * Processes the submitted Form input
	 * @param string $data
	 * @return string
	 */
	public function processInput( $data ) {
		foreach ( $this->replacements as $set ) {
			$data =
				preg_replace_callback( $set['search'],
					// select the correct replacement callback depending on $allowimgcode
					$set['replace'], $data );
		}
		return $data;
	}
}
