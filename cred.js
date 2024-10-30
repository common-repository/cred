var Cred = function() {
	var self;
	var credBaseUrl;
	var pluginUrl;
	var loggedIn;
	var connectWin;	
	var provider;
	var poller;
	var username;
	var authToken;
	var postUri;
	var isSubscription;
	var title;
	var cost;	
	var isLoading;
	var subscriptions;
	var srcElement;
	var isInit;
	
	// constructor
	function constructorFn() {
		self = this;
		loggedIn = false;
		isLoading = null;
		isSubscription = false;
		isInit = false;
		isCredUser = false;
		
		subscriptions = null;
		srcElement = null;
	}
	
	// initializer - to set server variables into object
	constructorFn.prototype.init = function(pBaseUrl, pProvider, pLoggedIn, pPluginUrl,
			pIsCredUser) {
		credBaseUrl = pBaseUrl;
		provider = pProvider;
		loggedIn = pLoggedIn;
		pluginUrl = pPluginUrl;
		
		if (!loggedIn && !window.cred_auth_error) {
			isInit = true;
			_auto_login();
		}
		
		if (!pIsCredUser) {
			loggedIn = false;
		}
	}

	// load subscription data
	constructorFn.prototype.init_subscriptions = function(data) {
		subscriptions = data;
	}
	
	// user wants to make a purchase
	constructorFn.prototype.purchase = function(pSrcElement, pPostUri, pTitle, pCost) {
		srcElement = pSrcElement;
		postUri = pPostUri;
		title = unescape(pTitle.replace(/\+/g, " "));
		cost = pCost;
	
		if (isLoading) return; // still busy
		
		if (!loggedIn) {
			// Get login authorisation using XSS
			xssLoad(credBaseUrl + '/connect/login?p=' + provider);     
		} else {
			purchaseContent();
		}
	}
	
	constructorFn.prototype._checkTimedOut = function() {
		if (isLoading) {
			isLoading.removeScriptTag();
			hideDiv("credMessage");
			self.error('Unable to contact YourCred.com. Please try again later.');
			isLoading = null;
		}
	}
	
	// Cred callback - logged in
	constructorFn.prototype.login = function(pUsername, pToken) {
		isLoading = false;
		isInit = false;
		username = pUsername;
		authToken = pToken;
		purchaseContent();
	}
	
	// Cred callback - not logged in
	constructorFn.prototype.not_logged_in = function() {
		isLoading = false;
		/*alert("You are not signed into Cred. Sign in to YourCred.com, then try again.\n" + 
			  "For security reasons, do not click any links to YourCred.com, rather enter\n" +
			  "it directly into your browser.");
	    */

		hideDiv("credMessage");
		if (!isInit) {
			if (confirm("You need to sign into Cred to view and redeem content. Sign in now?\n" +
					"If you are new to Cred, please click OK and read the FAQ for more information.")) {
				location.href = credBaseUrl + '/login?p=' + provider + '&r=' + escape(location.href);
			}
		}
		isInit = false;
	}

	// Cred callback with token for purchase authorisation
	constructorFn.prototype.purchaseAuthorisation = function(postUri, authCode) {
		isLoading = false;
		if (!isSubscription) {
			postData([ "cred_purchase_post_uri:"+postUri, "token:"+authCode ]);
		} else {
			postData([ "cred_subscription_uri:"+postUri, "token:"+authCode ]);
		}
	}
	
	// submit data to the wordpress plugin to purchase the data
	function purchaseContent() {
		if (!loggedIn) {
			postData([
			          "cred_login:true", "username:"+username, "usercode:"+authToken
		    ]);
			
			document.getElementById("credMessageText").innerHTML = "Logging into Cred, please wait...";
			showDiv("credMessage");
			
			return;
			
			/*
			if (confirm('Would you like to log in using your Cred account to view and redeem content?')) {
				postData([
				   "cred_login:true", "username:"+username, "usercode:"+authToken
			    ]);
				
				return;
			} else {
				return;
			}*/
		}
		
		document.getElementById("redeemCredValue").innerHTML = cost;		
		showDiv("redeemCred");
		
		/*
		var subs = "";
		if (subscriptions != null && subscriptions.length > 0) {
			subs = "\nPlease note that " + subscriptions.length + " subscriptions are available!";
		}
			
		if (confirm('Redeem "' + title + '" for ' + cost + " cred?" + subs)) {
			self._doPurchase();
		}
		*/
	}	
	
	// callback from confirmation screen
	constructorFn.prototype._doPurchase = function() {
		var div = document.getElementById("redeemCred");
		div.style.display='none';

		// check if the user wants to subscribe rather
		var theForm = document.forms['credRedeemForm'];
		if (theForm == null) {
			return;
		}

		if (theForm['cred_subscription_uri']) {
			// check if user selected to subscribe rather
			if (theForm['cred_subscription_uri'].length) {
				for (var i=0; i < theForm['cred_subscription_uri'].length; i++) {
					var radio = theForm['cred_subscription_uri'][i];
					if (radio.checked && radio.value != '') {
						postUri = radio.value;
						isSubscription = true;
					}
				}
			} else {
				var radio = theForm['cred_subscription_uri'];
				if (radio.checked && radio.value != '') {
					postUri = radio.value;
					isSubscription = true;
				}
			}
		}
		
		// get a purchase authorisation token using XSS
		xssLoad(credBaseUrl + '/connect/purchase?p=' + provider + '&uri=' + postUri);     
	}
	
	// user error messaging
	constructorFn.prototype.error = function(message) {
		alert("Cred error: " + message);
	}

	// wait for document to be ready, then auto-login the user
	function _auto_login() {
		if (document.body == null) {
			setTimeout(_auto_login, 500);
			return;
		}
		
		// Get login authorisation using XSS
		var obj = new JSONscriptRequest(credBaseUrl + '/connect/login?p=' + provider);     
		obj.buildScriptTag(); // Build the script tag     
	    obj.addScriptTag(); // Execute (add) the script tag		
	}	
	
	function showMessage(message) {
		try {
			document.getElementById("credMessageText").innerHTML = message;
			showDiv("credMessage");
		} catch (e) {
		}
	}
	
	// show a centered div
	function showDiv(divId) {
		var div = document.getElementById(divId);
		if (!div) return;

		div.style.visibility='hidden';
		div.style.display='block';

		var ht = div.style.offsetHeight || 300;
		var coord = window.center({width: 240, height: ht });
		div.style.top = "" + coord.y + "px";
		div.style.left = "" + coord.x + "px";
		div.style.position = 'absolute';
		div.style.visibility='visible';
	}
	
	function hideDiv(divId) {
		var div = document.getElementById(divId);
		if (!div) return;
		div.style.display = 'none';
	}
	
	// use XSS to load data from YourCred
	function xssLoad(url) {
		showMessage("Contacting Cred...");
		
		var obj = new JSONscriptRequest(url);     
		obj.buildScriptTag(); // Build the script tag     
	    obj.addScriptTag(); // Execute (add) the script tag
	    isLoading = obj;
	    
	    setTimeout(self._checkTimedOut, 20000);
	}
	
	// sanitizer
	function h(message) {
		var m = message;
		
		if (m.replace) {
			//m.replace(/'/g, "\\'");
			//m = m.replace(/"/g, '\\"');
			//m = m.replace(/</g, "&lt;");
			//m = m.replace(/>/g, "&gt;");
		}	
		
		return m;
	}
	
	// Post data to the wordpress plugin using a form post
	function postData(data) {
		var f = document.createElement("form");
		f.method = "post";
		f.action = "";
		
		for (var i=0; i < data.length; i++) {
			var h = document.createElement("input");
			h.type = "hidden";
			h.name = data[i].split(":")[0];
			h.value = data[i].split(":")[1];
			f.appendChild(h);
		}
		document.body.appendChild(f);
		//alert(f.innerHTML);
		f.submit();
	}
	
	return new constructorFn();
}

var cred = new Cred();