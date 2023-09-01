<% if EcommerceAlsoBoughtProducts %>
<div id="EcommerceAlsoBoughtProducts">
    <h3><% _t("YOUMAYALSO", "You may also be interested in the following products") %></h3>
    <ul class="productList">
        <% loop EcommerceAlsoBoughtProducts %><% include Sunnysideup\EcommerceAlsoBought\IncludesProductGroupItem %><% end_loop %>
    </ul>
</div>
<% end_if %>
