package br.com.shapefit.app;

import android.os.Bundle;
import android.graphics.Color;
import android.webkit.WebView;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {

        getWindow().getDecorView().setBackgroundColor(Color.BLACK);

        super.onCreate(savedInstanceState);


        WebView webView = (WebView) bridge.getWebView();
        if (webView != null) {
            webView.setBackgroundColor(Color.BLACK);
        }

        overridePendingTransition(0, 0);
    }
}
