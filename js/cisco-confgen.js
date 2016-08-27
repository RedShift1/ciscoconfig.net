function statusIdle()
{
    $("img#statusImage").attr("src", "img/ok.png");
    $("span#statusText").html('Doing nothing');
}

function statusWorking()
{
    $("img#statusImage").attr("src", "img/loading.gif");
    $("span#statusText").html('Working');
}

        
var mainObj = function()
{

    var ns = null;

    var preferences = $.initNamespaceStorage('ciscoconfig.net');

    var templateOptions;


    var request = {
        "f": "getTemplates"
    };

    $.ajax({
        type: "GET",
        dataType: "json",
        url: "json.php",
        data: request,
        success: function(data) {
            
            if(!data['success']) {
                return;
            }
            
            var template = '';
            $.each(data['data'], function(index, value) {
                template += '<li>';
                template += '<input type="radio" name="template" id="' + index + '" value="' + index + '" />';
                template += '<label for="' + index + '">' + value + '</label>';
                template += '</li>';
            }
            );
            $('ul#templateList').append(template);
            if(preferences.localStorage.isSet('defaultTemplate')) {
                var defTemplate = preferences.localStorage.get('defaultTemplate');
                $("input[name=template][value=" + defTemplate + "]").prop('checked', true);
                $("input[name=template][value=" + defTemplate + "]").click();
            }
            statusIdle();
        }
            
    });

    
    $("input#makeTemplateDefault").change(
        function()
        {
            if($('input#makeTemplateDefault').prop('checked')) {
                preferences.localStorage.set('defaultTemplate', $("input[name=template]:checked").val());
            } else {
                preferences.localStorage.remove('defaultTemplate');
            }

        }
    );

    
    $("#optionsForm").submit(
        function(e)
        {
            e.preventDefault();
            statusWorking();
            
            var request = {
                "f": "generate",
                "template": $("input[name=template]:checked").val(),
                "options": $("#optionsForm").serializeArray()
            };
            
            
            if($("#makedefault").prop('checked')) {
                $.each(templateOptions,
                    function(groupName, groupOpts)
                    {
                        $.each(groupOpts,
                            function(optName, optDef)
                            {
                                if(optDef["Type"] == "bool") {
                                    ns.localStorage.set(optDef["Name"], $("input[name=" + optDef["Name"] + "]:checked").val());
                                } else if(optDef["Type"] == "listOfListOfOpts") {
                                    var store = {};
                                    $.each(optDef["Value"],
                                        function(subOptName, subOptDef)
                                        {
                                            store[subOptName] = [];
                                            var fieldName = optName + "[" + subOptName + "]";
                                            $("input[name='" + fieldName + "[]']").each(
                                                function()
                                                {
                                                    store[subOptName].push($(this).val());
                                                }
                                            );
                                        }
                                    );
                                    ns.localStorage.set(optDef["Name"], JSON.stringify(store));
                                } else if (optDef["Type"].match('select:(.*)')) {
                                    ns.localStorage.set(optDef["Name"], $("select[name=" + optDef["Name"] + "] option:selected").val());
                                } else {
                                    ns.localStorage.set(optDef["Name"], $("input[name=" + optDef["Name"] + "]").val());
                                }
                            }
                        );
                    }

                );
            }


            
            $.ajax({
                type: "GET",
                dataType: "json",
                url: "json.php",
                data: request,
                success: function(data) {
                    

                    if(!data['success']) {
                        return;
                    }
                    
                    $("#config").html(data['data']);
                    statusIdle();
                }
            
            });
        }
    );
    
    $("#selectAll").click(
        function() {
            $("#config").select();
        }
            
    );
    
    $(document).on("click", "input[name=template]",
        function() {
            statusWorking();
            ns = $.initNamespaceStorage('ciscoconfig.net.' + $("input[name=template]:checked").val());
            var request = {
                "f": "getTemplateOptionsByGroup",
                "template": $("input[name=template]:checked").val()
            };
            
            
            if(preferences.localStorage.get('defaultTemplate') == $("input[name=template]:checked").val()) {
                $('input#makeTemplateDefault').prop('checked', true);
            } else {
                $('input#makeTemplateDefault').prop('checked', false);
            }

            
            $.ajax({
                type: "GET",
                dataType: "json",
                url: "json.php",
                data: request,
                context: this,
                success: function(data) {
                    
            
                    if(!data['success']) {
                        return;
                    }
                    
                    $("#options").html('<li><label>Name</label>Value</li>');
                    
                    templateOptions = data["data"];
                    
                    $("#options").append(generateOptList(data["data"]));
                    

                    statusIdle();
                }
            }
            );

        $("#optionsSection").show();

        }
    );

    function generateOptList(data) {
        var options = "";

        $.each(data,
            function(groupName, groupOpts) {

                options += '<li class="optGroupHeader">' + groupName + '</li>';
                $.each(groupOpts,
                    function(optName, value) {
                            options += '<li>';
                            options += '<label for="' + optName + '">' + optName + ':</label>';
                            options += '<div class="input">';
                            options += '<img src="img/resettodefault.png" onclick="javascript:resetOpt(\'' + groupName + '\', \'' + optName + '\')" title="Reset to default" /> ';
                            if(value["Type"] == 'listOfListOfOpts') {
                                options += generateListOfListOfOpts(optName, value["Value"], value["Name"]);
                            } else {

                               options += generateOpt(value, null, ns.localStorage.get(optName));

                            }
                            options += '<p class="description">' + value["Description"] + '&nbsp;</p>';
                            options += '</div>';
                            options += '</li>';

                        }
                    );
            }
        );
        return options;
    }

    /**
     * Generate an input field for a single option
     * @param {type} opt
     * @returns {unresolved} The HTML code for the single option
     */
    function generateOpt(opt, fieldName, value)
    {

        fieldName = fieldName || opt["Name"];

        var result = "";

        if(opt["Type"] === 'bool') {
            if(value === 'true' || opt["Value"] === true) {
                result += '<input name="' + fieldName + '" type="radio" value="true" checked="checked" />';
            } else {
                result += '<input name="' + fieldName + '" type="radio" value="true" />';
            }
            result += 'Yes';
            if(value === 'false' || opt["Value"] === false) {
                result += '<input name="' + fieldName + '" type="radio" value="false" checked="checked" />';
            } else {
                result += '<input name="' + fieldName + '" type="radio" value="false" />';
            }
            result += 'No';
        } else if(selectOptions = opt["Type"].match('select:(.*)')) {
            result += '<select name="' + fieldName + '">';
            $.each(selectOptions[1].split(','),
                function(optIndex, optValue)
                {
                    if(optValue == value) {
                        result += '<option selected="selected">' + optValue + '</option>';
                    } else {
                        result += '<option>' + optValue + '</option>';
                    }
                }
            );
            result += '</select>';
        } else {
            if(value) {
                result += '<input name="' + fieldName + '" type="text" size="' + opt["InputSize"] + '" value="' + value +'" />';
            } else {
                result += '<input name="' + fieldName + '" type="text" size="' + opt["InputSize"] + '" value="' + opt["Value"] +'" />';
            }
        }
        return result;
    }

        
    function generateListOfListOfOpts(listName, list, listId, ignoreLocalStorage)
    {
        
        ignoreLocalStorage = ignoreLocalStorage || false;
        
        var tmp = "", thead = "", tbody = "";
        tmp += '<table id="' + listId +'" class="list">';
        $.each(list,
            function(optName, value)
            {
                thead += '<th>' + optName + '<span class="description">' + value['Description'] + '</span></th>';
            }
        );

        if(ns.localStorage.isSet(listId) && ignoreLocalStorage === false) {
            parsed = JSON.parse(ns.localStorage.get(listId));
            var rowdata = [];
            $.each(list,
                function(optName, value)
                {
                    for(i = 0; i < parsed[optName].length; i++) {
                        if(!rowdata[i]) {
                            rowdata[i] = "";
                        }
                        rowdata[i] += "<td>" + generateOpt(value, listName + "[" + optName + "][]", parsed[optName][i]) + "</td>";
                    }
                }
            );
    
            for(i = 0; i < rowdata.length; i++) {
                tbody += "<tr>" + rowdata[i];
                if(i > 0) {
                    tbody += '<td class="removeRow"><img src="img/list-remove.png"> Remove row</td>';
                }
                tbody += "</tr>";
            }

        } else {
            $.each(list,
                function(optName, value)
                {
                    tbody += '<td>';
                    tbody += generateOpt(value, listName + "[" + optName + "][]");
                    tbody += '</td>';
                }
            );
        }
        

        tmp += '<thead>';
        tmp += thead;
        tmp += '</thead>';
        tmp += '<tbody>';
        tmp += tbody;
        tmp += '</tbody>';

        tmp += '<tfoot><tr><td colspan="10" class="addRow"><img src="img/list-add.png"> Add row</td></tr></tfoot>';

        tmp += '</table>';

        return tmp;
    }

    $(document).on("click", ".removeRow",
        function()
        {
            $(this).closest("tr").remove();
        }
    );

    $(document).on("click", ".addRow",
        function()
        {
            var tr = $(this).parent().parent().parent().find("tbody tr:first").html();
            tr += '<td class="removeRow"><img src="img/list-remove.png"> Remove row</td>';
            $(this).parent().parent().parent().find("tbody").append("<tr>" + tr + "</tr>");
        }
    );


    this.resetOpt = function(groupName, optName)
    {
        var opt = templateOptions[groupName][optName]
        if(opt["Type"] == "string") {
            $("[name=" + optName + "]").val(opt["Value"]);
        } else if (opt["Type"] == 'bool') {
            $("[name=" + optName + "]").prop('checked', false);
            $("[name=" + optName + "][value=" + opt["Value"] + "]").prop('checked', true);
        } else if (opt["Type"] == "listOfListOfOpts") {
            console.log(optName);
            $("#" + optName).html(
                generateListOfListOfOpts(optName, opt["Value"], opt["Name"], true)
            );
        }
    }

}

$("document").ready(mainObj);

function resetOpt(groupName, optName)
{
    mainObj.resetOpt(groupName, optName);
}


function saveTextAsFile()
{
	var textToWrite = document.getElementById("config").value;
	var textFileAsBlob = new Blob([textToWrite], {type:'text/plain'});
	
        if($('input[name=optFQDNHostname]').length != 0) {
            var fileNameToSaveAs = $('input[name=optFQDNHostname]').val() + '.conf';
        } else {
            var fileNameToSaveAs = 'cisco.conf';
        }

	var downloadLink = document.createElement("a");
	downloadLink.download = fileNameToSaveAs;
	downloadLink.innerHTML = "Download File";
	if (window.webkitURL != null)
	{
		// Chrome allows the link to be clicked
		// without actually adding it to the DOM.
		downloadLink.href = window.webkitURL.createObjectURL(textFileAsBlob);
	}
	else
	{
		// Firefox requires the link to be added to the DOM
		// before it can be clicked.
		downloadLink.href = window.URL.createObjectURL(textFileAsBlob);
		downloadLink.onclick = destroyClickedElement;
		downloadLink.style.display = "none";
		document.body.appendChild(downloadLink);
	}

	downloadLink.click();
}
