/*
 * YUI Compressor
 * http://developer.yahoo.com/yui/compressor/
 * Author: Julien Lecomte -  http://www.julienlecomte.net/
 * Author: Isaac Schlueter - http://foohack.com/
 * Author: Stoyan Stefanov - http://phpied.com/
 * Copyright (c) 2011 Yahoo! Inc.  All rights reserved.
 * The copyrights embodied in the content of this file are licensed
 * by Yahoo! Inc. under the BSD (revised) open source license.
 */
package com.yahoo.platform.yui.compressor;

import java.io.IOException;
import java.io.Reader;
import java.io.Writer;
import java.util.regex.Pattern;
import java.util.regex.Matcher;
import java.util.ArrayList;

public class CssCompressor {

    private StringBuffer srcsb = new StringBuffer();

    public CssCompressor(Reader in) throws IOException {
        // Read the stream...
        int c;
        while ((c = in.read()) != -1) {
            srcsb.append((char) c);
        }
    }

    // Leave data urls alone to increase parse performance.
    protected String extractDataUrls(String css, ArrayList preservedTokens) {

    	int maxIndex = css.length() - 1;
        int appendIndex = 0;

    	StringBuffer sb = new StringBuffer();

        Pattern p = Pattern.compile("url\\(\\s*([\"']?)data\\:");
        Matcher m = p.matcher(css);
        
        /* 
         * Since we need to account for non-base64 data urls, we need to handle 
         * ' and ) being part of the data string. Hence switching to indexOf,
         * to determine whether or not we have matching string terminators and
         * handling sb appends directly, instead of using matcher.append* methods.
         */

        while (m.find()) {

        	int startIndex = m.start() + 4;  	// "url(".length()
    		String terminator = m.group(1);     // ', " or empty (not quoted)
    		
    		if (terminator.length() == 0) {
    		 	terminator = ")";
    		}

    		boolean foundTerminator = false;

    		int endIndex = m.end() - 1;
    		while(foundTerminator == false && endIndex+1 <= maxIndex) {
    			endIndex = css.indexOf(terminator, endIndex+1);

    			if ((endIndex > 0) && (css.charAt(endIndex-1) != '\\')) {
    				foundTerminator = true;
    				if (!")".equals(terminator)) {
    					endIndex = css.indexOf(")", endIndex); 
    				}
    			}
    		}

    		// Enough searching, start moving stuff over to the buffer
			sb.append(css.substring(appendIndex, m.start()));

    		if (foundTerminator) {
    			String token = css.substring(startIndex, endIndex);
    			token = token.replaceAll("\\s+", "");
	    		preservedTokens.add(token);

	    		String preserver = "url(___YUICSSMIN_PRESERVED_TOKEN_" + (preservedTokens.size() - 1) + "___)";
	    		sb.append(preserver);

	    		appendIndex = endIndex + 1;
    		} else {
    			// No end terminator found, re-add the whole match. Should we throw/warn here?
    			sb.append(css.substring(m.start(), m.end()));
    			appendIndex = m.end();
    		}
        }

        sb.append(css.substring(appendIndex));

        return sb.toString();
    }

    public void compress(Writer out, int linebreakpos)
            throws IOException {

        Pattern p;
        Matcher m;
        String css = srcsb.toString();

        int startIndex = 0;
        int endIndex = 0;
        int i = 0;
        int max = 0;
        ArrayList preservedTokens = new ArrayList(0);
        ArrayList comments = new ArrayList(0);
        String token;
        int totallen = css.length();
        String placeholder;

        css = this.extractDataUrls(css, preservedTokens);

        StringBuffer sb = new StringBuffer(css);

        // collect all comment blocks...
        while ((startIndex = sb.indexOf("/*", startIndex)) >= 0) {
            endIndex = sb.indexOf("*/", startIndex + 2);
            if (endIndex < 0) {
                endIndex = totallen;
            }

            token = sb.substring(startIndex + 2, endIndex);
            comments.add(token);
            sb.replace(startIndex + 2, endIndex, "___YUICSSMIN_PRESERVE_CANDIDATE_COMMENT_" + (comments.size() - 1) + "___");
            startIndex += 2;
        }
        css = sb.toString();

        // preserve strings so their content doesn't get accidentally minified
        sb = new StringBuffer();
        p = Pattern.compile("(\"([^\\\\\"]|\\\\.|\\\\)*\")|(\'([^\\\\\']|\\\\.|\\\\)*\')");
        m = p.matcher(css);
        while (m.find()) {
            token = m.group();
            char quote = token.charAt(0);
            token = token.substring(1, token.length() - 1);

            // maybe the string contains a comment-like substring?
            // one, maybe more? put'em back then
            if (token.indexOf("___YUICSSMIN_PRESERVE_CANDIDATE_COMMENT_") >= 0) {
                for (i = 0, max = comments.size(); i < max; i += 1) {
                    token = token.replace("___YUICSSMIN_PRESERVE_CANDIDATE_COMMENT_" + i + "___", comments.get(i).toString());
                }
            }

            // minify alpha opacity in filter strings
            token = token.replaceAll("(?i)progid:DXImageTransform.Microsoft.Alpha\\(Opacity=", "alpha(opacity=");

            preservedTokens.add(token);
            String preserver = quote + "___YUICSSMIN_PRESERVED_TOKEN_" + (preservedTokens.size() - 1) + "___" + quote;
            m.appendReplacement(sb, preserver);
        }
        m.appendTail(sb);
        css = sb.toString();


        // strings are safe, now wrestle the comments
        for (i = 0, max = comments.size(); i < max; i += 1) {

            token = comments.get(i).toString();
            placeholder = "___YUICSSMIN_PRESERVE_CANDIDATE_COMMENT_" + i + "___";

            // ! in the first position of the comment means preserve
            // so push to the preserved tokens while stripping the !
            if (token.startsWith("!")) {
                preservedTokens.add(token);
                css = css.replace(placeholder,  "___YUICSSMIN_PRESERVED_TOKEN_" + (preservedTokens.size() - 1) + "___");
                continue;
            }

            // \ in the last position looks like hack for Mac/IE5
            // shorten that to /*\*/ and the next one to /**/
            if (token.endsWith("\\")) {
                preservedTokens.add("\\");
                css = css.replace(placeholder,  "___YUICSSMIN_PRESERVED_TOKEN_" + (preservedTokens.size() - 1) + "___");
                i = i + 1; // attn: advancing the loop
                preservedTokens.add("");
                css = css.replace("___YUICSSMIN_PRESERVE_CANDIDATE_COMMENT_" + i + "___",  "___YUICSSMIN_PRESERVED_TOKEN_" + (preservedTokens.size() - 1) + "___");
                continue;
            }

            // keep empty comments after child selectors (IE7 hack)
            // e.g. html >/**/ body
            if (token.length() == 0) {
                startIndex = css.indexOf(placeholder);
                if (startIndex > 2) {
                    if (css.charAt(startIndex - 3) == '>') {
                        preservedTokens.add("");
                        css = css.replace(placeholder,  "___YUICSSMIN_PRESERVED_TOKEN_" + (preservedTokens.size() - 1) + "___");
                    }
                }
            }

            // in all other cases kill the comment
            css = css.replace("/*" + placeholder + "*/", "");
        }


        // Normalize all whitespace strings to single spaces. Easier to work with that way.
        css = css.replaceAll("\\s+", " ");

        // Remove the spaces before the things that should not have spaces before them.
        // But, be careful not to turn "p :link {...}" into "p:link{...}"
        // Swap out any pseudo-class colons with the token, and then swap back.
        sb = new StringBuffer();
        p = Pattern.compile("(^|\\})(([^\\{:])+:)+([^\\{]*\\{)");
        m = p.matcher(css);
        while (m.find()) {
            String s = m.group();
            s = s.replaceAll(":", "___YUICSSMIN_PSEUDOCLASSCOLON___");
            s = s.replaceAll( "\\\\", "\\\\\\\\" ).replaceAll( "\\$", "\\\\\\$" );
            m.appendReplacement(sb, s);
        }
        m.appendTail(sb);
        css = sb.toString();
        // Remove spaces before the things that should not have spaces before them.
        css = css.replaceAll("\\s+([!{};:>+\\(\\)\\],])", "$1");
        // bring back the colon
        css = css.replaceAll("___YUICSSMIN_PSEUDOCLASSCOLON___", ":");

        // retain space for special IE6 cases
        css = css.replaceAll(":first\\-(line|letter)(\\{|,)", ":first-$1 $2");

        // no space after the end of a preserved comment
        css = css.replaceAll("\\*/ ", "*/");

        // If there is a @charset, then only allow one, and push to the top of the file.
        css = css.replaceAll("^(.*)(@charset \"[^\"]*\";)", "$2$1");
        css = css.replaceAll("^(\\s*@charset [^;]+;\\s*)+", "$1");

        // Put the space back in some cases, to support stuff like
        // @media screen and (-webkit-min-device-pixel-ratio:0){
        css = css.replaceAll("\\band\\(", "and (");

        // Remove the spaces after the things that should not have spaces after them.
        css = css.replaceAll("([!{}:;>+\\(\\[,])\\s+", "$1");

        // remove unnecessary semicolons
        css = css.replaceAll(";+}", "}");

        // Replace 0(px,em,%) with 0.
        css = css.replaceAll("([\\s:])(0)(px|em|%|in|cm|mm|pc|pt|ex)", "$1$2");

        // Replace 0 0 0 0; with 0.
        css = css.replaceAll(":0 0 0 0(;|})", ":0$1");
        css = css.replaceAll(":0 0 0(;|})", ":0$1");
        css = css.replaceAll(":0 0(;|})", ":0$1");


        // Replace background-position:0; with background-position:0 0;
        // same for transform-origin
        sb = new StringBuffer();
        p = Pattern.compile("(?i)(background-position|transform-origin|webkit-transform-origin|moz-transform-origin|o-transform-origin|ms-transform-origin):0(;|})");
        m = p.matcher(css);
        while (m.find()) {
            m.appendReplacement(sb, m.group(1).toLowerCase() + ":0 0" + m.group(2));
        }
        m.appendTail(sb);
        css = sb.toString();

        // Replace 0.6 to .6, but only when preceded by : or a white-space
        css = css.replaceAll("(:|\\s)0+\\.(\\d+)", "$1.$2");

        // Shorten colors from rgb(51,102,153) to #336699
        // This makes it more likely that it'll get further compressed in the next step.
        p = Pattern.compile("rgb\\s*\\(\\s*([0-9,\\s]+)\\s*\\)");
        m = p.matcher(css);
        sb = new StringBuffer();
        while (m.find()) {
            String[] rgbcolors = m.group(1).split(",");
            StringBuffer hexcolor = new StringBuffer("#");
            for (i = 0; i < rgbcolors.length; i++) {
                int val = Integer.parseInt(rgbcolors[i]);
                if (val < 16) {
                    hexcolor.append("0");
                }
                hexcolor.append(Integer.toHexString(val));
            }
            m.appendReplacement(sb, hexcolor.toString());
        }
        m.appendTail(sb);
        css = sb.toString();

        // Shorten colors from #AABBCC to #ABC. Note that we want to make sure
        // the color is not preceded by either ", " or =. Indeed, the property
        //     filter: chroma(color="#FFFFFF");
        // would become
        //     filter: chroma(color="#FFF");
        // which makes the filter break in IE.
        // We also want to make sure we're only compressing #AABBCC patterns inside { }, not id selectors ( #FAABAC {} )
        // We also want to avoid compressing invalid values (e.g. #AABBCCD to #ABCD)
        p = Pattern.compile("(\\=\\s*?[\"']?)?" + "#([0-9a-fA-F])([0-9a-fA-F])([0-9a-fA-F])([0-9a-fA-F])([0-9a-fA-F])([0-9a-fA-F])" + "(:?\\}|[^0-9a-fA-F{][^{]*?\\})");

        m = p.matcher(css);
        sb = new StringBuffer();
        int index = 0;

        while (m.find(index)) {

        	sb.append(css.substring(index, m.start()));

        	boolean isFilter = (m.group(1) != null && !"".equals(m.group(1))); 

        	if (isFilter) {
        		// Restore, as is. Compression will break filters
        		sb.append(m.group(1) + "#" + m.group(2) + m.group(3) + m.group(4) + m.group(5) + m.group(6) + m.group(7));
        	} else {
        		if( m.group(2).equalsIgnoreCase(m.group(3)) &&
                    m.group(4).equalsIgnoreCase(m.group(5)) &&
                    m.group(6).equalsIgnoreCase(m.group(7))) {

	        		// #AABBCC pattern
	                sb.append("#" + (m.group(3) + m.group(5) + m.group(7)).toLowerCase());

        		} else {

        			// Non-compressible color, restore, but lower case.
        			sb.append("#" + (m.group(2) + m.group(3) + m.group(4) + m.group(5) + m.group(6) + m.group(7)).toLowerCase());
        		}
            }

        	index = m.end(7);
        }

        sb.append(css.substring(index));
        css = sb.toString();

        // border: none -> border:0
        sb = new StringBuffer();
        p = Pattern.compile("(?i)(border|border-top|border-right|border-bottom|border-right|outline|background):none(;|})");
        m = p.matcher(css);
        while (m.find()) {
            m.appendReplacement(sb, m.group(1).toLowerCase() + ":0" + m.group(2));
        }
        m.appendTail(sb);
        css = sb.toString();

        // shorter opacity IE filter
        css = css.replaceAll("(?i)progid:DXImageTransform.Microsoft.Alpha\\(Opacity=", "alpha(opacity=");

        // Remove empty rules.
        css = css.replaceAll("[^\\}\\{/;]+\\{\\}", "");

        // TODO: Should this be after we re-insert tokens. These could alter the break points. However then
        // we'd need to make sure we don't break in the middle of a string etc.
        if (linebreakpos >= 0) {
            // Some source control tools don't like it when files containing lines longer
            // than, say 8000 characters, are checked in. The linebreak option is used in
            // that case to split long lines after a specific column.
            i = 0;
            int linestartpos = 0;
            sb = new StringBuffer(css);
            while (i < sb.length()) {
                char c = sb.charAt(i++);
                if (c == '}' && i - linestartpos > linebreakpos) {
                    sb.insert(i, '\n');
                    linestartpos = i;
                }
            }

            css = sb.toString();
        }

        // Replace multiple semi-colons in a row by a single one
        // See SF bug #1980989
        css = css.replaceAll(";;+", ";");

        // restore preserved comments and strings
        for(i = 0, max = preservedTokens.size(); i < max; i++) {
            css = css.replace("___YUICSSMIN_PRESERVED_TOKEN_" + i + "___", preservedTokens.get(i).toString());
        }

        // Trim the final string (for any leading or trailing white spaces)
        css = css.trim();

        // Write the output...
        out.write(css);
    }
}
