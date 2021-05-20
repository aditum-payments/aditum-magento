require(['jquery','ko'], function () {
    // Konduto
    function getAntiFraudType() {
        if (typeof window.checkoutConfig.payment.aditumboleto.antifraud_type !== 'undefined') {
            return window.checkoutConfig.payment.aditumboleto.antifraud_type;
        }
        else if (typeof window.checkoutConfig.payment.aditumcc.antifraud_type !== 'undefined') {
            return window.checkoutConfig.payment.aditumcc.antifraud_type;
        }
        return false;
    }
    function getAntiFraudId() {
        if (typeof window.checkoutConfig.payment.aditumboleto.antifraud_id !== 'undefined') {
            return window.checkoutConfig.payment.aditumboleto.antifraud_id;
        }
        else if (typeof window.checkoutConfig.payment.aditumcc.antifraud_id !== 'undefined') {
            return window.checkoutConfig.payment.aditumcc.antifraud_id;
        }
        return false;
    }
    function loadKonduto() {
        try {
            (() => {
                const period = 300;
                const limit = 20 * 1e3;
                let nTry = 0;
                const intervalID = setInterval(() => {
                    const kondutoObj = (window).Konduto;
                    let clear = limit / period <= ++nTry;
                    if (
                        typeof kondutoObj !== "undefined" &&
                        typeof kondutoObj.getVisitorID !== "undefined"
                    ) {
                        const visitorID = kondutoObj.getVisitorID();
                        //this.kondutoVisitorID = visitorID;
                        console.log("loadKonduto");
                        console.log(visitorID);
                        document.getElementById('antifraud_token').innerHTML = visitorID;
                        clear = true;
                    }
                    if (clear) {
                        clearInterval(intervalID);
                    }
                }, period);
            })();
            return;
        } catch (error) {
            return;
        }
    }

    function loadClearSale(publicKey) {
        try {
            (() => {
                const period = 300;
                const limit = 20 * 1e3;
                let nTry = 0;
                const intervalID = setInterval(() => {
                    const csdpObj = (window).csdp;
                    const csdmObj = (window).csdm;

                    let clear = limit / period <= ++nTry;
                    if (
                        typeof csdpObj !== "undefined" &&
                        typeof csdmObj !== "undefined" &&
                        typeof publicKey !== "undefined"
                    ) {
                        (window).csdp("app", publicKey);
                        (window).csdp("outputsessionid", "clearsaleSessionId");
                        (window).csdm('app', publicKey);
                        (window).csdm('mode', 'manual');
                        (window).csdm("send", "checkout");
                        console.log( (window).csdp);
                        console.log( (window).csdm);
                        console.log("Clear Sale");
                        console.log(publicKey);
                        clear = true;
                    }
                    if (clear) {
                        clearInterval(intervalID);
                    }
                }, period);
            })();
            return;
        } catch (error) {
            return;
        }
    }
    if (getAntiFraudType()=='konduto') {
        try {
            const pk = getAntiFraudId();
            // @ts-ignore
            window.__kdt = window.__kdt || [];
            // @ts-ignore
            window.__kdt.push({public_key: pk});
            (() => {
                console.log('linha 14');
                const kdt = document.createElement("script");
                kdt.id = "kdtjs";
                kdt.type = "text/javascript";
                kdt.async = true;
                kdt.src = "https://i.k-analytix.com/k.js";
                const s = document.getElementsByTagName("body")[0];
                (s.parentNode).insertBefore(kdt, s);
            })();
            loadKonduto();
        } catch (error) {
        }
    }
    // CLear Sale
    if (getAntiFraudType()=='clearsale') {
        ((a, b, c, d, e, f, g) => {
            a['CsdpObject'] = e;
            a[e] = a[e] || function () {
                (a[e].q = a[e].q || []).push(arguments)
            }
            // @ts-ignore
            a[e].l = 1 * new Date();
            f = b.createElement(c),
                g = b.getElementsByTagName(c)[0];
            // @ts-ignore
            f.src = d;
            // @ts-ignore
            f.async = true;
            // @ts-ignore
            g.parentNode.insertBefore(f, g)
        })(window, document, 'script', '//device.clearsale.com.br/p/fp.js', 'csdp');
        (function (a, b, c, d, e, f, g) {
            a['CsdmObject'] = e;
            a[e] = a[e] || function () {
                (a[e].q = a[e].q || []).push(arguments)
            };
            // @ts-ignore
            a[e].l = 1 * new Date();
            f = b.createElement(c);
            g = b.getElementsByTagName(c)[0];
            // @ts-ignore
            f.src = d;
            // @ts-ignore
            f.async = true;
            // @ts-ignore
            g.parentNode.insertBefore(f, g);
        })(window, document, 'script', '//device.clearsale.com.br/m/cs.js', 'csdm')
        loadClearSale(getAntiFraudId());
    }

});
