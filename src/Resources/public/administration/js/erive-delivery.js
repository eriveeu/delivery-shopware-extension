(this.webpackJsonp=this.webpackJsonp||[]).push([["erive-delivery"],{"23ho":function(e,t,i){"use strict";i.r(t);i("Joik");var s=i("wrnk"),n=i.n(s);const{Component:r,Mixin:o}=Shopware;r.register("erive-api-test-button",{template:n.a,props:["label"],inject:["eriveApiTest"],mixins:[o.getByName("notification")],data:()=>({isLoading:!1,isSaveSuccessful:!1}),computed:{pluginConfig(){let e=this.$parent;for(;void 0===e.actualConfigData;)e=e.$parent;return e.actualConfigData.null}},methods:{saveFinish(){this.isSaveSuccessful=!1},check(){this.isLoading=!0,this.eriveApiTest.check(this.pluginConfig).then((e=>{200===e.status||"OK"===e.statusText||e.success?(this.isSaveSuccessful=!0,this.createNotificationSuccess({title:this.$tc("erive-api-test-button.title"),message:this.$tc("erive-api-test-button.success")})):this.createNotificationError({title:this.$tc("erive-api-test-button.title"),message:this.$tc("erive-api-test-button.error")})})).catch((e=>{this.createNotificationError({title:this.$tc("erive-api-test-button.title"),message:this.$tc("erive-api-test-button.error")})})).finally((()=>{this.isLoading=!1}))}}});var c=i("367a"),a=i("zsxP");Shopware.Locale.extend("de-DE",c),Shopware.Locale.extend("en-GB",a)},"367a":function(e){e.exports=JSON.parse('{"erive-api-test-button":{"title":"API Test","success":"Verbindung wurde erfolgreich getestet","error":"Verbindung konnte nicht hergestellt werden. Bitte prüfe die Zugangsdaten","button":"Test"}}')},Joik:function(e,t){const{Application:i}=Shopware;class s{constructor(e){this.httpClient=e}check(e){let t="",i=e["EriveDelivery.config.apiTestKey"].trim();const s=e["EriveDelivery.config.eriveEnvironment"].trim();switch(s){case"www":t=`https://${s}.erive.delivery/api/v1`,i=e["EriveDelivery.config.apiKey"].trim();break;case"custom":t=e["EriveDelivery.config.customApiEndpoint"].trim();break;default:t=`https://${s}.greentohome.at/api/v1`}return t=t.endsWith("/")?t.substring(0,t.length-1):t,this.httpClient.get(`${t}/company/parcelsFrom?key=${i}`)}}i.addServiceProvider("eriveApiTest",(e=>{const t=i.getContainer("init");return new s(t.httpClient)}))},wrnk:function(e,t){e.exports='<div>\n    <sw-button-process\n        :isLoading="isLoading"\n        :processSuccess="isSaveSuccessful"\n        @process-finish="saveFinish"\n        @click="check">\n            {{ $tc(\'erive-api-test-button.button\') }}\n        </sw-button-process>\n</div>'},zsxP:function(e){e.exports=JSON.parse('{"erive-api-test-button":{"title":"API Test","success":"Connection was successfully tested","error":"Connection could not be established. Please check the access data","button":"Test"}}')}},[["23ho","runtime"]]]);