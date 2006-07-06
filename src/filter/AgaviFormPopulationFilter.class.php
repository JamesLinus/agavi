<?php

// +---------------------------------------------------------------------------+
// | This file is part of the Agavi package.                                   |
// | Copyright (c) 2003-2006 the Agavi Project.                                |
// |                                                                           |
// | For the full copyright and license information, please view the LICENSE   |
// | file that was distributed with this source code. You can also view the    |
// | LICENSE file online at http://www.agavi.org/LICENSE.txt                   |
// |   vi: set noexpandtab:                                                    |
// |   Local Variables:                                                        |
// |   indent-tabs-mode: t                                                     |
// |   End:                                                                    |
// +---------------------------------------------------------------------------+

/**
 * AgaviFormPopulationFilter automatically populates a form that is re-posted,
 * which usually happens when a View::INPUT is returned again after a POST 
 * request because an error occured during validation.
 * That means that developers don't have to fill in request parameters into
 * form elements in their templates anymore. Text inputs, selects, radios, they
 * all get set to the value the user selected before submitting the form.
 * If you would like to set default values, you still have to do that in your
 * template. The filter will recognize this situation and automatically remove
 * the default value you assigned after receiving a POST request.
 * This filter only works with POST requests, and compares the form's URL and
 * the requested URL to decide if it's appropriate to fill in a specific form
 * it encounters while processing the output document sent back to the browser.
 * Since this form is executed very late in the process, it works independently
 * of any template language.
 *
 * <b>Optional parameters:</b>
 *

 * # <b>cdata_fix</b> - [true] - Fix generated CDATA delimiters in script and 
 *                               style blocks.
 * # <b>error_class</b>'- "error"'- The class name that is assigned to form 
 *                                  elements which didn't pass validation and 
 *                                  their labels.
 * # <b>force_output_mode</b> - [false] - If false, the output mode (XHTML or 
 *                                        HTML) will be auto-detected using the 
 *                                        document's DOCTYPE declaration. Set 
 *                                        this to "html" or "xhtml" to force 
 *                                        one of these output modes explicitly.
 * # <b>include_hidden_inputs</b> - [false]'- If hidden input fields should be 
 *                                            re-populated.
 * # <b>include_password_inputs</b> - [false] - If password input fields should 
 *                                              be re-populated.
 * # <b>remove_xml_prolog</b> - [true] - If the XML prolog generated by DOM 
 *                                       should be removed (existing ones will 
 *                                       remain untouched).
 *
 * @package    agavi
 * @subpackage filter
 *
 * @author     David Zuelke <dz@bitxtender.com>
 * @copyright  (c) Authors
 * @since      0.11.0
 *
 * @version    $Id$
 */
class AgaviFormPopulationFilter extends AgaviFilter implements AgaviIGlobalFilter
{
	/**
	 * Execute this filter.
	 *
	 * @param      AgaviFilterChain The filter chain.
	 *
	 * @throws     <b>AgaviFilterException</b> If an error occurs during execution.
	 *
	 * @author     David Zuelke <dz@bitxtender.com>
	 * @since      0.11.0
	 */
	public function execute(AgaviFilterChain $filterChain, AgaviResponse $response)
	{
		$filterChain->execute($filterChain, $response);
		
		$req = $this->getContext()->getRequest();
		
		if(!($req->getAttribute('populate', 'org.agavi.filter.FormPopulationFilter') === true || (in_array($req->getMethod(), $this->getParameter('methods')) && $req->getAttribute('populate', 'org.agavi.filter.FormPopulationFilter') !== false))) {
			return;
		}
		
		$output = $response->getContent();
		
		$doc = DOMDocument::loadHTML($output);
		$hasXmlProlog = false;
		if(preg_match('#<\?xml.*?\?>#iuU', $output)) {
			$hasXmlProlog = true;
		}
		$xpath = new DomXPath($doc);
		$baseHref = '';
		foreach($xpath->query('//head/base[@href]') as $base) {
			$baseHref = parse_url($base->getAttribute('href'));
			$baseHref = $baseHref['path'];
			break;
		}
		foreach($xpath->query('//form[@action]') as $form) {
			$action = $form->getAttribute('action');
			if(!($baseHref . $action == $_SERVER['REQUEST_URI'] || $baseHref . '/' . $action == $_SERVER['REQUEST_URI'] || (strpos($action, '/') === 0 && $action == $_SERVER['REQUEST_URI']) || (strlen($_SERVER['REQUEST_URI']) == strrpos($_SERVER['REQUEST_URI'], $action) + strlen($action)))) {
				continue;
			}
			
			// build the XPath query
			$query = 'descendant::textarea[@name] | descendant::select[@name] | descendant::input[@name and not(@type)] | descendant::input[@name and @type="text"] | descendant::input[@name and @type="checkbox"] | descendant::input[@name and @type="radio"] | descendant::input[@name and @type="password"]';
			if($this->getParameter('include_hidden_inputs')) {
				$query .= ' | descendant::input[@name and @type="hidden"]';
			}

			foreach($xpath->query($query, $form) as $element) {
				
				// there's an error with the element's name in the request? good. let's give the baby a class!
				if($req->hasError($element->getAttribute('name'))) {
					$element->setAttribute('class', $element->getAttribute('class') . ' ' . $this->getParameter('error_class'));
					// assign the class to all implicit labels
					foreach($xpath->query('ancestor::label[not(@for)]', $element) as $label) {
						$label->setAttribute('class', $label->getAttribute('class') . ' ' . $this->getParameter('error_class'));
					}
					if(($id = $element->getAttribute('id')) != '') {
						// assign the class to all explicit labels
						foreach($xpath->query('descendant::label[@for="' . $id . '"]', $form) as $label) {
							$label->setAttribute('class', $label->getAttribute('class') . ' ' . $this->getParameter('error_class'));
						}
					}
				}
				
				if(strpos($element->getAttribute('name'), '[]') !== false) {
					// auto-generated index, we can't populate that
					continue;
				}
				
				if($element->nodeName == 'input') {
					
					if(!$element->hasAttribute('type') || $element->getAttribute('type') == 'text' || $element->getAttribute('type') == 'hidden') {
						
						// text inputs
						$element->removeAttribute('value');
						if($req->hasParameter($element->getAttribute('name'))) {
							$element->setAttribute('value', $req->getParameter($element->getAttribute('name')));
						}
						
					} elseif($element->getAttribute('type') == 'checkbox' || $element->getAttribute('type') == 'radio') {
						
						// checkboxes and radios
						$element->removeAttribute('checked');
						if($req->hasParameter($element->getAttribute('name')) && ($element->getAttribute('value') == $req->getParameter($element->getAttribute('name')) || !$element->hasAttribute('value'))) {
							$element->setAttribute('checked', 'checked');
						}
						
					} elseif($element->getAttribute('type') == 'password') {
						
						// passwords
						$element->removeAttribute('value');
						if($this->getParameter('include_password_inputs') && $req->hasParameter($element->getAttribute('name'))) {
							$element->setAttribute('value', $req->getParameter($element->getAttribute('name')));
						}
					}
					
				} elseif ($element->nodeName == 'select') {
					
					// select elements
					// yes, we still use XPath because there could be OPTGROUPs
					foreach($xpath->query('descendant::option', $element) as $option) {
						$option->removeAttribute('selected');
						if($req->hasParameter($element->getAttribute('name')) && $option->getAttribute('value') == $req->getParameter($element->getAttribute('name'))) {
							$option->setAttribute('selected', 'selected');
						}
					}
					
				} elseif($element->nodeName == 'textarea') {
					
					// textareas
					foreach($element->childNodes as $cn) {
						// remove all child nodes (= text nodes)
						$element->removeChild($cn);
					}
					// append a new text node
					$element->appendChild($doc->createTextNode($req->getParameter($element->getAttribute('name'))));
				}
				
			}
		}
		if(strtolower($this->getParameter('force_output_mode')) == 'xhtml' || ($doc->doctype && stripos($doc->doctype->publicId, '-//W3C//DTD XHTML') === 0 && strtolower($this->getParameter('force_output_mode')) != 'html')) {
			$out = $doc->saveXML();
			if($this->getParameter('cdata_fix')) {
				// these are ugly fixes so inline style and script blocks still work. better don't use them with XHTML to avoid trouble
				$out = preg_replace('#<style([^>]*)>\s*<!\[CDATA\[#iuU', '<style\\1><!--/*--><![CDATA[/*><!--*/', $out);
				$out = preg_replace('#\]\]></style>#iuU', '/*]]>*/--></style>', $out);
				$out = preg_replace('#<script([^>]*)>\s*<!\[CDATA\[#iuU', '<script\\1><!--//--><![CDATA[//><!--', $out);
				$out = preg_replace('#\]\]></script>#iuU', '//--><!]]></script>', $out);
			}
			if($this->getParameter('remove_xml_prolog') && !$hasXmlProlog) {
				// there was no xml prolog in the document before, so we remove the one generated by DOM now
				$out = preg_replace('#<\?xml.*?\?>\s#iuU', '', $out);
			}
			$response->setContent($out);
		} else {
			$reponse->setContent($doc->saveHTML());
		}
	}

	/**
	 * Initialize this filter.
	 *
	 * @param      AgaviContext The current application context.
	 * @param      array        An associative array of initialization parameters.
	 *
	 * @throws     <b>AgaviFilterException</b> If an error occurs during initialization
	 *
	 * @author     David Zuelke <dz@bitxtender.com>
	 * @since      0.11.0
	 */
	public function initialize(AgaviContext $context, $parameters = array())
	{
		// set defaults
		$this->setParameter('cdata_fix', true);
		$this->setParameter('error_class', 'error');
		$this->setParameter('force_output_mode', false);
		$this->setParameter('include_password_inputs', false);
		$this->setParameter('include_hidden_inputs', true);
		$this->setParameter('remove_xml_prolog', true);
		$this->setParameter('methods', "");
		// initialize parent
		parent::initialize($context, $parameters);
		// build array of request methods
		$this->setParameter('methods', explode(' ', $this->getParameter('methods')));
	}
}

?>