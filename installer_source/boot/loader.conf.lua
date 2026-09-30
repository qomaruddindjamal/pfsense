if _ENV["smbios.system.maker"] == "Netgate" then
	if _ENV["smbios.system.product"] == "4100" or _ENV["smbios.system.product"] == "6100" or _ENV["smbios.system.product"] == "6200" or _ENV["smbios.system.product"] == "8200" then
		print("Netgate Cordoba System detected.")
		_ENV["console"]="efi"
		_ENV["hint.cordbuc.0.at"]="isa"
		_ENV["hint.cordbuc.0.port"]="0x800"

		_ENV["hint.gpioled.0.at"]="gpiobus0"
		_ENV["hint.gpioled.0.pins"]="0x001"
		_ENV["hint.gpioled.0.name"]="red1"
		_ENV["hint.gpioled.0.invert"]="1"

		_ENV["hint.gpioled.1.at"]="gpiobus0"
		_ENV["hint.gpioled.1.pins"]="0x002"
		_ENV["hint.gpioled.1.name"]="green1"
		_ENV["hint.gpioled.1.invert"]="1"

		_ENV["hint.gpioled.2.at"]="gpiobus0"
		_ENV["hint.gpioled.2.pins"]="0x004"
		_ENV["hint.gpioled.2.name"]="blue1"
		_ENV["hint.gpioled.2.invert"]="1"

		_ENV["hint.gpioled.3.at"]="gpiobus0"
		_ENV["hint.gpioled.3.pins"]="0x008"
		_ENV["hint.gpioled.3.name"]="amber1"
		_ENV["hint.gpioled.3.invert"]="1"

		_ENV["hint.gpioled.4.at"]="gpiobus0"
		_ENV["hint.gpioled.4.pins"]="0x010"
		_ENV["hint.gpioled.4.name"]="red2"
		_ENV["hint.gpioled.4.invert"]="1"

		_ENV["hint.gpioled.5.at"]="gpiobus0"
		_ENV["hint.gpioled.5.pins"]="0x020"
		_ENV["hint.gpioled.5.name"]="green2"
		_ENV["hint.gpioled.5.invert"]="1"

		_ENV["hint.gpioled.6.at"]="gpiobus0"
		_ENV["hint.gpioled.6.pins"]="0x040"
		_ENV["hint.gpioled.6.name"]="blue2"
		_ENV["hint.gpioled.6.invert"]="1"

		_ENV["hint.gpioled.7.at"]="gpiobus0"
		_ENV["hint.gpioled.7.pins"]="0x080"
		_ENV["hint.gpioled.7.name"]="amber2"
		_ENV["hint.gpioled.7.invert"]="1"

		_ENV["hint.gpioled.8.at"]="gpiobus0"
		_ENV["hint.gpioled.8.pins"]="0x100"
		_ENV["hint.gpioled.8.name"]="red3"
		_ENV["hint.gpioled.8.invert"]="1"

		_ENV["hint.gpioled.9.at"]="gpiobus0"
		_ENV["hint.gpioled.9.pins"]="0x200"
		_ENV["hint.gpioled.9.name"]="green3"
		_ENV["hint.gpioled.9.invert"]="1"

		_ENV["hint.gpioled.10.at"]="gpiobus0"
		_ENV["hint.gpioled.10.pins"]="0x400"
		_ENV["hint.gpioled.10.name"]="blue3"
		_ENV["hint.gpioled.10.invert"]="1"

		_ENV["hint.gpioled.11.at"]="gpiobus0"
		_ENV["hint.gpioled.11.pins"]="0x800"
		_ENV["hint.gpioled.11.name"]="amber3"
		_ENV["hint.gpioled.11.invert"]="1"
	end
	if _ENV["smbios.system.product"] == "4200" then
		print("Netgate 4200 detected.")
		_ENV["console"]="efi"
		_ENV["hw.uart.console"]="mm:0xfe03e000"
		_ENV["dev.igc.0.iflib.override_nrxqs"]="1";
		_ENV["dev.igc.1.iflib.override_nrxqs"]="1";
		_ENV["dev.igc.2.iflib.override_nrxqs"]="1";
		_ENV["dev.igc.3.iflib.override_nrxqs"]="1";
	end
	if _ENV["smbios.system.product"] == "8300" then
		print("Netgate 8300 detected.")
		_ENV["ice_ddp_load"]="yes"
		_ENV["led_8300_load"]="yes"
		_ENV["igpio_load"]="yes"
		_ENV["kern.crypto.iimb.max_threads"]="12"
	end
end

if string.sub(_ENV["smbios.planar.product"], 1, 11) == "80300-0134-" then
	print("Netgate 7100 detected.")
	_ENV["boot_serial"]="YES"
	_ENV["console"]="comconsole"
	_ENV["hint.mdio.0.at"]="ix2"
	_ENV["hint.e6000sw.0.addr"]="0"
	_ENV["hint.e6000sw.0.is8190"]="1"
	_ENV["hint.e6000sw.0.port0disabled"]="1"
	_ENV["hint.e6000sw.0.port9cpu"]="1"
	_ENV["hint.e6000sw.0.port10cpu"]="1"
	_ENV["hint.e6000sw.0.port9speed"]="2500"
	_ENV["hint.e6000sw.0.port10speed"]="2500"
end

if _ENV["smbios.system.product"] == "DFFv2" or _ENV["smbios.system.product"] == "RCC" or _ENV["smbios.system.product"] == "RCC-VE" then
	print("Netgate RCC detected.")
	_ENV["boot_serial"]="YES"
	_ENV["console"]="comconsole"
	_ENV["comconsole_port"]="0x2F8"
	_ENV["hint.uart.0.flags"]="0x00"
	_ENV["hint.uart.1.flags"]="0x10"
end

comconsole_speed="115200"
